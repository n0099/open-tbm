using OpenCvSharp;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class ImageOcrConsumer
{
    private readonly string _script;
    private readonly PaddleOcrRecognizerAndDetector _paddleOcrRecognizerAndDetector;
    private readonly TesseractRecognizer _tesseractRecognizer;
    private readonly int _gridSizeToMergeBoxesIntoSingleLine;
    private readonly int _paddleOcrConfidenceThreshold;
    private readonly int _percentageThresholdOfIntersectionAreaToConsiderAsSameTextBox;
    private readonly int _percentageThresholdOfIntersectionAreaToConsiderAsNewTextBox;

    public delegate ImageOcrConsumer New(string script);

    public ImageOcrConsumer(IConfiguration config, string script,
        PaddleOcrRecognizerAndDetector.New paddleOcrRecognizerAndDetectorFactory, TesseractRecognizer tesseractRecognizer)
    {
        _script = script;
        _paddleOcrRecognizerAndDetector = paddleOcrRecognizerAndDetectorFactory(script);
        _tesseractRecognizer = tesseractRecognizer;
        var configSection = config.GetSection("ImageOcrPipeline");
        _gridSizeToMergeBoxesIntoSingleLine = configSection.GetValue("GridSizeToMergeBoxesIntoSingleLine", 10);
        _paddleOcrConfidenceThreshold = configSection.GetSection("PaddleOcr").GetValue("ConfidenceThreshold", 80);
        var tesseractConfigSection = configSection.GetSection("Tesseract");
        _percentageThresholdOfIntersectionAreaToConsiderAsSameTextBox =
            tesseractConfigSection.GetValue("PercentageThresholdOfIntersectionAreaToConsiderAsSameTextBox", 90);
        _percentageThresholdOfIntersectionAreaToConsiderAsNewTextBox =
            tesseractConfigSection.GetValue("PercentageThresholdOfIntersectionAreaToConsiderAsNewTextBox", 10);
    }

    public Task InitializePaddleOcrModel(CancellationToken stoppingToken = default) =>
        _paddleOcrRecognizerAndDetector.InitializeModel(stoppingToken);

    public Dictionary<string, string> GetRecognizedTextLinesKeyByImageId(Dictionary<string, Mat> imagesKeyByUrlFilename)
    {
        var recognizedResultsByPaddleOcr =
            _paddleOcrRecognizerAndDetector.RecognizeImageMatrices(imagesKeyByUrlFilename).ToList();
        var detectionResults = _paddleOcrRecognizerAndDetector.DetectImageMatrices(imagesKeyByUrlFilename);
        var recognizedResultsByTesseract = GetRecognizedResultsByTesseract(
            recognizedResultsByPaddleOcr, detectionResults, imagesKeyByUrlFilename).ToList();
        var recognizedResultsGroupByImageId = recognizedResultsByPaddleOcr
            .Where<IRecognitionResult>(result => result.Confidence >= _paddleOcrConfidenceThreshold)
            .Concat(recognizedResultsByTesseract.Where(result => !result.ShouldFallbackToPaddleOcr))
            .Concat(recognizedResultsByPaddleOcr.IntersectBy(recognizedResultsByTesseract
                .Where(result => result.ShouldFallbackToPaddleOcr)
                .Select(result => result.TextBox), result => result.TextBox))
            .GroupBy(result => result.ImageId);
        return recognizedResultsGroupByImageId.ToDictionary(g => g.Key, g =>
        {
            var resultTextLines = g
                .Select(result =>
                {
                    var rect = result.TextBox.BoundingRect();
                    // align to a virtual grid to prevent a single line that splitting into multiple text boxes
                    // which have similar but different values of y coordinates get rearranged in a wrong order
                    var alignedY = ((double)rect.Y / _gridSizeToMergeBoxesIntoSingleLine).RoundToUshort();
                    return (result, rect.X, alignedY);
                })
                .OrderBy(t => t.alignedY).ThenBy(t => t.X)
                .GroupBy(t => t.alignedY, t => t.result)
                .Select(groupByLine =>
                    string.Join("\n", groupByLine.Select(result => result.Text.Trim())));
            return string.Join('\n', resultTextLines).Normalize(NormalizationForm.FormKC); // https://unicode.org/reports/tr15/
        });
    }

    private record CorrelatedTextBoxPair(string ImageId, ushort PercentageOfIntersection,
        RotatedRect DetectedTextBox, RotatedRect RecognizedTextBox);

    private IEnumerable<TesseractRecognitionResult> GetRecognizedResultsByTesseract(
        IReadOnlyCollection<PaddleOcrRecognitionResult> recognizedResultsByPaddleOcr,
        IEnumerable<PaddleOcrRecognizerAndDetector.DetectionResult> detectionResults,
        IReadOnlyDictionary<string, Mat> imageMatricesKeyByImageId)
    {
        ushort GetPercentageOfIntersectionArea(RotatedRect subject, RotatedRect clip)
        {
            var intersectType = Cv2.RotatedRectangleIntersection(subject, clip, out var intersectingRegionPoints);
            if (intersectType == RectanglesIntersectTypes.Full) return 100;
            if (intersectType == RectanglesIntersectTypes.None) return 0;
            var intersectionArea = Cv2.ContourArea(intersectingRegionPoints);
            var areas = new[]
            {
                intersectionArea / Cv2.ContourArea(subject.Points()),
                intersectionArea / Cv2.ContourArea(clip.Points())
            };
            return (areas.Where(area => !double.IsNaN(area)).Average() * 100).RoundToUshort();
        }
        var uniqueRecognizedResults = recognizedResultsByPaddleOcr
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
            select g.Where(pair => pair.PercentageOfIntersection > _percentageThresholdOfIntersectionAreaToConsiderAsSameTextBox)
                .DefaultIfEmpty().MaxBy(pair => pair?.PercentageOfIntersection) into pair
            where pair != default
            select pair
        ).ToLookup(pair => pair.ImageId);
        var unrecognizedDetectedTextBoxes = (
            from pair in correlatedTextBoxPairs
            group pair by pair.DetectedTextBox into g
            where g.All(pair => pair.PercentageOfIntersection < _percentageThresholdOfIntersectionAreaToConsiderAsNewTextBox)
            select g.MinBy(pair => pair.PercentageOfIntersection)
        ).ToLookup(pair => pair.ImageId);

        return recognizedResultsByPaddleOcr
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
                return TesseractRecognizer.PreprocessTextBoxes(imageId, imageMatricesKeyByImageId[imageId], boxes);
            })
            .SelectMany(textBoxes => textBoxes.SelectMany(b =>
                _tesseractRecognizer.RecognizePreprocessedTextBox(_script, b)));
    }
}
