using System.Text;

namespace tbm.ImagePipeline.Ocr;

public class JointRecognizer(
    IConfiguration config,
    PaddleOcrRecognizerAndDetector.New paddleOcrRecognizerAndDetectorFactory,
    TesseractRecognizer.New tesseractRecognizerFactory,
    FailedImageHandler failedImageHandler,
    string script)
{
    private readonly PaddleOcrRecognizerAndDetector _paddleOcrRecognizerAndDetector = paddleOcrRecognizerAndDetectorFactory(script);
    private readonly Lazy<TesseractRecognizer> _tesseractRecognizer = new(() => tesseractRecognizerFactory(script));
    private readonly IConfigurationSection _config = config.GetSection("OcrConsumer");

    public delegate JointRecognizer New(string script);

    private int GridSizeToMergeBoxesIntoSingleLine => _config.GetValue("GridSizeToMergeBoxesIntoSingleLine", 10);
    private int PaddleOcrConfidenceThreshold => _config.GetValue("PaddleOcr:ConfidenceThreshold", 80);
    private (int ToConsiderAsSameTextBox, int ToConsiderAsNewTextBox) IntersectionAreaThresholds
    {
        get
        {
            var intersectionAreaThresholdConfigSection =
                _config.GetSection("Tesseract:IntersectionAreaThreshold");
            return (
                intersectionAreaThresholdConfigSection.GetValue("ToConsiderAsSameTextBox", 90),
                intersectionAreaThresholdConfigSection.GetValue("ToConsiderAsNewTextBox", 10));
        }
    }

    public async Task InitializePaddleOcr(CancellationToken stoppingToken = default) =>
        await _paddleOcrRecognizerAndDetector.Initialize(stoppingToken);

    public IEnumerable<Either<ImageId, IRecognitionResult>> RecognizeMatrices
        (Dictionary<ImageKey, Mat> matricesKeyByImageKey, CancellationToken stoppingToken = default)
    {
        var recognizedEithersViaPaddleOcr = _paddleOcrRecognizerAndDetector
            .RecognizeMatrices(matricesKeyByImageKey, failedImageHandler, stoppingToken).ToList();
        var detectedEithers = _paddleOcrRecognizerAndDetector
            .DetectMatrices(matricesKeyByImageKey, failedImageHandler, stoppingToken).ToList();
        var recognizedResultsViaPaddleOcr =
            recognizedEithersViaPaddleOcr.Rights().SelectMany(i => i).ToList();
        var recognizedEithersViaTesseract = RecognizeMatricesViaTesseract(
                recognizedResultsViaPaddleOcr,
                detectedEithers.Rights().SelectMany(i => i).ToList(),
                matricesKeyByImageKey,
                stoppingToken)
            .ToList();
        var recognizedResultsViaTesseract =
            recognizedEithersViaTesseract.Rights().ToList();
        return recognizedResultsViaPaddleOcr
            .Where<IRecognitionResult>(result => result.Confidence >= PaddleOcrConfidenceThreshold)
            .Concat(recognizedResultsViaTesseract.Where(result => !result.ShouldFallbackToPaddleOcr))
            .Concat(recognizedResultsViaPaddleOcr.IntersectBy(recognizedResultsViaTesseract
                    .Where(result => result.ShouldFallbackToPaddleOcr)
                    .Select(result => result.TextBox),
                result => result.TextBox))
            .Select(Either<ImageId, IRecognitionResult>.Right)
            .Concat(recognizedEithersViaPaddleOcr
                .Lefts()
                .Concat(detectedEithers.Lefts())
                .Concat(recognizedEithersViaTesseract.Lefts())
                .Select(Either<ImageId, IRecognitionResult>.Left));
    }

    public Dictionary<ImageKey, string> GetRecognizedTextLines
        (IEnumerable<IRecognitionResult> recognizedResults) => recognizedResults
        .GroupBy(result => result.ImageKey)
        .ToDictionary(g => g.Key, g =>
        {
            var resultTextLines = g
                .Select(result =>
                {
                    var rect = result.TextBox.BoundingRect();

                    // align to a virtual grid to prevent a single line that splitting into multiple text boxes
                    // which have similar but different values of y coordinates get rearranged in a wrong order
                    // https://github.com/sdcb/PaddleSharp/issues/55#issuecomment-1607067510
                    var alignedY = (double)rect.Y / GridSizeToMergeBoxesIntoSingleLine;

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

    private IEnumerable<Either<ImageId, TesseractRecognitionResult>> RecognizeMatricesViaTesseract(
        IReadOnlyCollection<PaddleOcrRecognitionResult> recognizedResultsViaPaddleOcr,
        IEnumerable<PaddleOcrRecognizerAndDetector.DetectionResult> detectionResults,
        IReadOnlyDictionary<ImageKey, Mat> matricesKeyByImageKey,
        CancellationToken stoppingToken = default)
    {
        byte GetPercentageOfIntersectionArea(RotatedRect subject, RotatedRect clip)
        {
            stoppingToken.ThrowIfCancellationRequested();
            var intersectType = Cv2.RotatedRectangleIntersection(subject, clip, out var intersectingRegionPoints);
            if (intersectType == RectanglesIntersectTypes.None) return 0;
            var intersectionArea = Cv2.ContourArea(intersectingRegionPoints);
            var areas = new[]
            {
                intersectionArea / Cv2.ContourArea(subject.Points()),
                intersectionArea / Cv2.ContourArea(clip.Points())
            };
            return (areas.Where(area => !double.IsNaN(area)).Average() * 100).RoundToByte();
        }
        var uniqueRecognizedResults = recognizedResultsViaPaddleOcr

            // not grouping by result.Script and ImageId to remove duplicated text boxes across all scripts of an image
            .GroupBy(result => result.ImageKey)
            .SelectMany(g => g.DistinctBy(result => result.TextBox));
        var correlatedTextBoxPairs = (
            from detectionResult in detectionResults
            join recognitionResult in uniqueRecognizedResults on detectionResult.ImageKey equals recognitionResult.ImageKey
            let percentageOfIntersection = GetPercentageOfIntersectionArea(detectionResult.TextBox, recognitionResult.TextBox)
            select new CorrelatedTextBoxPair(detectionResult.ImageKey, percentageOfIntersection,
                detectionResult.TextBox, recognitionResult.TextBox)
        ).ToList();

        var recognizedDetectedTextBoxes = (
            from pair in correlatedTextBoxPairs
            group pair by pair.RecognizedTextBox into g
            select g.Where(pair => pair.PercentageOfIntersection > IntersectionAreaThresholds.ToConsiderAsSameTextBox)
                .DefaultIfEmpty().MaxBy(pair => pair?.PercentageOfIntersection) into pair
            where pair != default
            select pair
        ).ToLookup(pair => pair.ImageKey);
        var unrecognizedDetectedTextBoxes = (
            from pair in correlatedTextBoxPairs
            group pair by pair.DetectedTextBox into g
            where g.All(pair => pair.PercentageOfIntersection <= IntersectionAreaThresholds.ToConsiderAsNewTextBox)
            select g.MinBy(pair => pair.PercentageOfIntersection)
        ).ToLookup(pair => pair.ImageKey);

        var preprocessedTextBoxEithers = recognizedResultsViaPaddleOcr
            .Where(result => result.Confidence < PaddleOcrConfidenceThreshold)
            .GroupBy(result => result.ImageKey)
            .Select(failedImageHandler.Try<
                IGrouping<ImageKey, PaddleOcrRecognitionResult>,
                IEnumerable<TesseractRecognizer.PreprocessedTextBox>
            >(
                g => g.Key.ImageId,
                g =>
                {
                    var imageKey = g.Key;
                    var boxes = recognizedDetectedTextBoxes[imageKey]
                        .IntersectBy(g.Select(result => result.TextBox), pair => pair.RecognizedTextBox)
                        .Select(pair => pair.DetectedTextBox)
                        .Concat(unrecognizedDetectedTextBoxes[imageKey].Select(pair => pair.DetectedTextBox));
                    return TesseractRecognizer.PreprocessTextBoxes(
                        imageKey, matricesKeyByImageKey[imageKey], boxes, stoppingToken).ToList();
                }))
            .ToList();
        var recognizedTextBoxEithers = preprocessedTextBoxEithers
            .Rights()
            .SelectMany(textBoxes => failedImageHandler.TrySelect(textBoxes,
                b => b.ImageKey.ImageId,
                _tesseractRecognizer.Value.RecognizePreprocessedTextBox(stoppingToken)))
            .ToList();
        return preprocessedTextBoxEithers
            .Lefts()
            .Concat(recognizedTextBoxEithers.Lefts())
            .Select(Either<ImageId, TesseractRecognitionResult>.Left)
            .Concat(recognizedTextBoxEithers
                .Rights()
                .Select(Either<ImageId, TesseractRecognitionResult>.Right));
    }

    private record CorrelatedTextBoxPair(
        ImageKey ImageKey, byte PercentageOfIntersection,
        RotatedRect DetectedTextBox, RotatedRect RecognizedTextBox);
}
