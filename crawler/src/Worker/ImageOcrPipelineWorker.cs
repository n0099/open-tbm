using Clipper2Lib;
using Emgu.CV;
using Emgu.CV.Util;
using OpenCvSharp;
using tbm.Crawler.ImagePipeline.Ocr;
using Mat = OpenCvSharp.Mat;
using Point = System.Drawing.Point;

namespace tbm.Crawler.Worker;

public class ImageOcrPipelineWorker : ErrorableWorker
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private static HttpClient _http = null!;
    private readonly PaddleOcrRecognizer _paddleOcrRecognizer;
    private readonly TesseractRecognizer _tesseractRecognizer;
    private readonly int _gridSizeToMergeBoxesIntoSingleLine;
    private readonly int _paddleOcrConfidenceThreshold;
    private readonly int _percentageThresholdOfIntersectionAreaToConsiderAsSameTextBox;
    private readonly int _percentageThresholdOfIntersectionAreaToConsiderAsNewTextBox;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IConfiguration config, IHttpClientFactory httpFactory,
        PaddleOcrRecognizer paddleOcrRecognizer, TesseractRecognizer tesseractRecognizer) : base(logger)
    {
        _logger = logger;
        _http = httpFactory.CreateClient("tbImage");
        _paddleOcrRecognizer = paddleOcrRecognizer;
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

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        var imagesUrlFilename = new List<string> {""};
        var imagesKeyByUrlFilename = (await Task.WhenAll(
                imagesUrlFilename.Select(async filename =>
                    (filename, bytes: await _http.GetByteArrayAsync(filename + ".jpg", stoppingToken)))))
            .SelectMany(t =>
            {
                Mat Flip(Mat mat, FlipMode flipMode)
                {
                    var ret = new Mat();
                    Cv2.Flip(mat, ret, flipMode);
                    return ret;
                }
                var (filename, bytes) = t;
                var mat = Cv2.ImDecode(bytes, ImreadModes.Color); // convert to BGR three channels without alpha
                return new (string Filename, Mat Mat)[]
                {
                    (filename, mat),
                    (filename + "-flip", Flip(mat, FlipMode.X)),
                    (filename + "-flop", Flip(mat, FlipMode.Y)),
                    (filename + "-flip-flop", Flip(mat, FlipMode.XY)) // same with 180 degrees clockwise rotation
                };
            })
            .ToDictionary(t => t.Filename, t => t.Mat);
        await _paddleOcrRecognizer.InitializeModels(stoppingToken);
        var recognizedResultsByPaddleOcr = _paddleOcrRecognizer.RecognizeImageMatrices(imagesKeyByUrlFilename).ToList();
        var detectionResults = _paddleOcrRecognizer.DetectImageMatrices(imagesKeyByUrlFilename);
        var recognizedResultsByTesseract = recognizedResultsByPaddleOcr
            .GroupBy(result => result.Script).Select(g =>
                GetRecognizedResultsByTesseract(g, detectionResults,
                    imagesKeyByUrlFilename.ToDictionary(pair => pair.Key, pair =>
                    {
                        Cv2.ImEncode(".png", pair.Value, out var ret);
                        return ret;
                    })));
        foreach (var groupByImageId in recognizedResultsByPaddleOcr
                     .Where<IRecognitionResult>(result => result.Confidence >= _paddleOcrConfidenceThreshold)
                     .Concat(recognizedResultsByTesseract.SelectMany(i => i))
                     .GroupBy(result => result.ImageId))
        {
            groupByImageId
                .GroupBy(result => result.Script)
                .SelectMany(scripts => scripts.Select(result =>
                        {
                            var rect = result.TextBox.ToCircumscribedRectangle();
                            // align to a virtual grid to prevent a single line that splitting into multiple text boxes
                            // which have similar but different values of y coordinates get rearranged in a wrong order
                            var alignedY = ((double)rect.Y / _gridSizeToMergeBoxesIntoSingleLine).RoundToUshort();
                            return (result, x: rect.X, alignedY);
                        })
                        .OrderBy(t => t.alignedY).ThenBy(t => t.x)
                        .GroupBy(t => t.alignedY, t => t.result),
                    (scripts, lines) => (script: scripts.Key, lines))
                .GroupBy(t => t.script, t => t.lines)
                .ForEach(groupByScript =>
                {
                    _logger.LogInformation("{} {}", groupByImageId.Key, groupByScript.Key);
                    var texts = string.Join("\n", groupByScript.Select(groupByLine =>
                            string.Join("\n", groupByLine.Select(result =>
                                {
                                    var tesseractExtraInfos = result is TesseractRecognitionResult r
                                        ? $" vert={r.IsVertical} unrecognized={r.IsUnrecognized} "
                                        : "";
                                    var circumscribedRectangle = result.TextBox.ToCircumscribedRectangle();
                                    return $"{circumscribedRectangle} {result.Confidence} {result.Text.Trim()} {tesseractExtraInfos}";
                                }))
                            .Trim()))
                        .Trim()
                        .Normalize(NormalizationForm.FormKC); // https://unicode.org/reports/tr15/
                    _logger.LogInformation("\n{}", texts);
                });
        }
        Environment.Exit(0);
    }

    private record CorrelatedTextBoxPair(string ImageId, ushort PercentageOfIntersection,
        PaddleOcrResponse.TextBox DetectedTextBox, PaddleOcrResponse.TextBox RecognizedTextBox);

    private IEnumerable<TesseractRecognitionResult> GetRecognizedResultsByTesseract(
        IGrouping<string, PaddleOcrRecognitionResult> recognizedResultsByPaddleOcrGroupByScript,
        IEnumerable<PaddleOcrRequester.DetectionResult> detectionResults,
        IReadOnlyDictionary<string, byte[]> imagesKeyByUrlFilename)
    {
        ushort GetPercentageOfIntersectionArea(PaddleOcrResponse.TextBox subject, PaddleOcrResponse.TextBox clip)
        {
            Paths64 ConvertTextBoxToPath(PaddleOcrResponse.TextBox b) => new() {Clipper.MakePath(new[] {
                b.TopLeft.X, b.TopLeft.Y, b.TopRight.X, b.TopRight.Y,
                b.BottomRight.X, b.BottomRight.Y, b.BottomLeft.X, b.BottomLeft.Y
            })};
            double GetContourArea(Paths64 paths) => paths.Any()
                ? CvInvoke.ContourArea(new VectorOfPoint(paths
                    .SelectMany(path => path.Select(point => new Point((int)point.X, (int)point.Y))).ToArray()))
                : 0;
            var subjectPaths = ConvertTextBoxToPath(subject);
            var clipPaths = ConvertTextBoxToPath(clip);
            // slower OpenCV approach without Clipper: https://stackoverflow.com/questions/17810681/intersection-area-of-2-polygons-in-opencv
            var intersectionArea = GetContourArea(Clipper.Intersect(subjectPaths, clipPaths, FillRule.NonZero));
            var areas = new[] {intersectionArea / GetContourArea(subjectPaths), intersectionArea / GetContourArea(clipPaths)};
            return (areas.Average() * 100).RoundToUshort();
        }
        var uniqueRecognizedResults = recognizedResultsByPaddleOcrGroupByScript
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

        return recognizedResultsByPaddleOcrGroupByScript
            .Where(result => result.Confidence < _paddleOcrConfidenceThreshold)
            .GroupBy(result => result.ImageId)
            .Select(g =>
            {
                var imageId = g.Key;
                var boxes = g.Select(result => recognizedDetectedTextBoxes[imageId]
                        .FirstOrDefault(pair => pair.RecognizedTextBox == result.TextBox)?.DetectedTextBox)
                    .OfType<PaddleOcrResponse.TextBox>()
                    .Select(b => (false, b))
                    .Concat(unrecognizedDetectedTextBoxes[imageId].Select(pair => pair.DetectedTextBox).Select(b => (true, b)));
                return TesseractRecognizer.PreprocessTextBoxes(imageId, imagesKeyByUrlFilename[imageId], boxes);
            })
            .SelectMany(textBoxes => textBoxes.SelectMany(b =>
                _tesseractRecognizer.RecognizePreprocessedTextBox(recognizedResultsByPaddleOcrGroupByScript.Key, b)));
    }
}
