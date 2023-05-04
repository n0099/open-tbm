using System.Text;

namespace tbm.ImagePipeline.Ocr;

public class ImageOcrConsumer
{
    private readonly PaddleOcrRecognizerAndDetector _paddleOcrRecognizerAndDetector;
    private readonly Lazy<TesseractRecognizer> _tesseractRecognizer;
    private readonly int _gridSizeToMergeBoxesIntoSingleLine;
    private readonly int _paddleOcrConfidenceThreshold;
    private readonly (int ToConsiderAsSameTextBox, int ToConsiderAsNewTextBox) _intersectionAreaThresholds;

    public delegate ImageOcrConsumer New(string script);

    public ImageOcrConsumer(IConfiguration config, string script,
        PaddleOcrRecognizerAndDetector.New paddleOcrRecognizerAndDetectorFactory, TesseractRecognizer.New tesseractRecognizerFactory)
    {
        _paddleOcrRecognizerAndDetector = paddleOcrRecognizerAndDetectorFactory(script);
        _tesseractRecognizer = new(() => tesseractRecognizerFactory(script));
        var configSection = config.GetSection("ImageOcrPipeline");
        _gridSizeToMergeBoxesIntoSingleLine = configSection.GetValue("GridSizeToMergeBoxesIntoSingleLine", 10);
        _paddleOcrConfidenceThreshold = configSection.GetSection("PaddleOcr").GetValue("ConfidenceThreshold", 80);
        var intersectionAreaThresholdConfigSection = configSection.GetSection("Tesseract").GetSection("IntersectionAreaThreshold");
        _intersectionAreaThresholds = (
            intersectionAreaThresholdConfigSection.GetValue("ToConsiderAsSameTextBox", 90),
            intersectionAreaThresholdConfigSection.GetValue("ToConsiderAsNewTextBox", 10));
    }

    public Task InitializePaddleOcr(CancellationToken stoppingToken = default) =>
        _paddleOcrRecognizerAndDetector.Initialize(stoppingToken);

    public IEnumerable<IRecognitionResult> RecognizeImageMatrices(Dictionary<ImageId, Mat> matricesKeyByImageId)
    {
        var recognizedResultsViaPaddleOcr =
            _paddleOcrRecognizerAndDetector.RecognizeImageMatrices(matricesKeyByImageId).ToList();
        var detectedResults =
            _paddleOcrRecognizerAndDetector.DetectImageMatrices(matricesKeyByImageId).ToList();
        var recognizedResultsViaTesseract = RecognizeImageMatricesViaTesseract(
            recognizedResultsViaPaddleOcr, detectedResults, matricesKeyByImageId).ToList();
        return recognizedResultsViaPaddleOcr
            .Where<IRecognitionResult>(result => result.Confidence >= _paddleOcrConfidenceThreshold)
            .Concat(recognizedResultsViaTesseract.Where(result => !result.ShouldFallbackToPaddleOcr))
            .Concat(recognizedResultsViaPaddleOcr.IntersectBy(recognizedResultsViaTesseract
                .Where(result => result.ShouldFallbackToPaddleOcr)
                .Select(result => result.TextBox), result => result.TextBox));
    }

    public Dictionary<ImageId, string> GetRecognizedTextLinesKeyByImageId
        (IEnumerable<IRecognitionResult> recognizedResults) => recognizedResults
        .GroupBy(result => result.ImageId)
        .ToDictionary(g => g.Key, g =>
        {
            var resultTextLines = g
                .Select(result =>
                {
                    var rect = result.TextBox.BoundingRect();
                    // align to a virtual grid to prevent a single line that splitting into multiple text boxes
                    // which have similar but different values of y coordinates get rearranged in a wrong order
                    var alignedY = (double)rect.Y / _gridSizeToMergeBoxesIntoSingleLine;
                    // the bounding rect for a rotated rect might be outside the original image
                    // so the y-axis coordinate of its top-left point can be negative
                    return (result, rect.X, alignedY: alignedY < 0 ? 0 : alignedY.RoundToUshort());
                })
                .OrderBy(t => t.alignedY).ThenBy(t => t.X)
                .GroupBy(t => t.alignedY, t => t.result)
                .Select(groupByLine =>
                    string.Join("\n", groupByLine.Select(result => result.Text.Trim())));
            return string.Join('\n', resultTextLines).Normalize(NormalizationForm.FormKC); // https://unicode.org/reports/tr15/
        });

    private record CorrelatedTextBoxPair(ImageId ImageId, ushort PercentageOfIntersection,
        RotatedRect DetectedTextBox, RotatedRect RecognizedTextBox);

    private IEnumerable<TesseractRecognitionResult> RecognizeImageMatricesViaTesseract(
        IReadOnlyCollection<PaddleOcrRecognitionResult> recognizedResultsViaPaddleOcr,
        IEnumerable<PaddleOcrRecognizerAndDetector.DetectionResult> detectionResults,
        IReadOnlyDictionary<ImageId, Mat> imageMatricesKeyByImageId)
    {
        static ushort GetPercentageOfIntersectionArea(RotatedRect subject, RotatedRect clip)
        {
            var intersectType = Cv2.RotatedRectangleIntersection(subject, clip, out var intersectingRegionPoints);
            if (intersectType == RectanglesIntersectTypes.None) return 0;
            var intersectionArea = Cv2.ContourArea(intersectingRegionPoints);
            var areas = new[]
            {
                intersectionArea / Cv2.ContourArea(subject.Points()),
                intersectionArea / Cv2.ContourArea(clip.Points())
            };
            return (areas.Where(area => !double.IsNaN(area)).Average() * 100).RoundToUshort();
        }
        var uniqueRecognizedResults = recognizedResultsViaPaddleOcr
            // not grouping by result.Script and ImageId to remove duplicated text boxes across all scripts of an image
            .GroupBy(result => result.ImageId).SelectMany(g => g.DistinctBy(result => result.TextBox));
        var correlatedTextBoxPairs = (
            from detectionResult in detectionResults
            join recognitionResult in uniqueRecognizedResults on detectionResult.ImageId equals recognitionResult.ImageId
            let percentageOfIntersection = GetPercentageOfIntersectionArea(detectionResult.TextBox, recognitionResult.TextBox)
            select new CorrelatedTextBoxPair(detectionResult.ImageId, percentageOfIntersection, detectionResult.TextBox, recognitionResult.TextBox)
        ).ToList();

        var recognizedDetectedTextBoxes = (
            from pair in correlatedTextBoxPairs
            group pair by pair.RecognizedTextBox into g
            select g.Where(pair => pair.PercentageOfIntersection > _intersectionAreaThresholds.ToConsiderAsSameTextBox)
                .DefaultIfEmpty().MaxBy(pair => pair?.PercentageOfIntersection) into pair
            where pair != default
            select pair
        ).ToLookup(pair => pair.ImageId);
        var unrecognizedDetectedTextBoxes = (
            from pair in correlatedTextBoxPairs
            group pair by pair.DetectedTextBox into g
            where g.All(pair => pair.PercentageOfIntersection <= _intersectionAreaThresholds.ToConsiderAsNewTextBox)
            select g.MinBy(pair => pair.PercentageOfIntersection)
        ).ToLookup(pair => pair.ImageId);

        return recognizedResultsViaPaddleOcr
            .Where(result => result.Confidence < _paddleOcrConfidenceThreshold)
            .GroupBy(result => result.ImageId)
            .Select(g =>
            {
                var imageId = g.Key;
                var boxes = recognizedDetectedTextBoxes[imageId]
                    .IntersectBy(g.Select(result => result.TextBox), pair => pair.RecognizedTextBox)
                    .Select(pair => (IsUnrecognized: false, pair.DetectedTextBox))
                    .Concat(unrecognizedDetectedTextBoxes[imageId]
                        .Select(pair => pair.DetectedTextBox).Select(b => (IsUnrecognized: true, b)));
                return TesseractRecognizer.PreprocessTextBoxes(imageId, imageMatricesKeyByImageId[imageId], boxes).ToList();
            })
            .SelectMany(textBoxes => textBoxes.Select(b =>
                _tesseractRecognizer.Value.RecognizePreprocessedTextBox(b)));
    }
}
