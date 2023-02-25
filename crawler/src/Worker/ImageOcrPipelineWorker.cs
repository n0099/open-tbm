using System.Drawing;
using Clipper2Lib;
using Emgu.CV;
using Emgu.CV.Util;
using tbm.Crawler.ImagePipeline.Ocr;

namespace tbm.Crawler.Worker;

public class ImageOcrPipelineWorker : ErrorableWorker
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private static HttpClient _http = null!;
    private readonly PaddleOcrRequester _requester;
    private readonly TesseractRecognizer _recognizer;
    private readonly int _gridSizeToMergeBoxesIntoSingleLine;
    private readonly float _paddleOcrConfidenceThreshold;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IConfiguration config,
        IHttpClientFactory httpFactory, PaddleOcrRequester requester, TesseractRecognizer recognizer) : base(logger)
    {
        _logger = logger;
        _http = httpFactory.CreateClient("tbImage");
        _requester = requester;
        _recognizer = recognizer;
        var configSection = config.GetSection("ImageOcrPipeline");
        _gridSizeToMergeBoxesIntoSingleLine = configSection.GetValue("GridSizeToMergeBoxesIntoSingleLine", 10);
        _paddleOcrConfidenceThreshold = configSection.GetValue("PaddleOcrConfidenceThreshold", 80f);
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        var imagesUrlFilename = new List<string> {""};
        var imagesKeyByUrlFilename = (await Task.WhenAll(
                imagesUrlFilename.Select(async filename =>
                    (filename, bytes: await _http.GetByteArrayAsync(filename + ".jpg", stoppingToken)))))
            .ToDictionary(t => t.filename, t => t.bytes);
        var recognizedResultsByPaddleOcr =
            (await _requester.RequestForRecognition(imagesKeyByUrlFilename, stoppingToken))
            .SelectMany(i => i).ToList();
        ushort GetPercentageOfIntersectionArea(PaddleOcrResponse.TextBox subject, PaddleOcrResponse.TextBox clip)
        {
            Paths64 ConvertTextBoxToPath(PaddleOcrResponse.TextBox b) =>
                new() {Clipper.MakePath(new[] {b.TopLeft.X, b.TopLeft.Y, b.TopRight.X, b.TopRight.Y,
                    b.BottomRight.X, b.BottomRight.Y, b.BottomLeft.X, b.BottomLeft.Y, b.TopLeft.X, b.TopLeft.Y})};
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
        var uniqueRecognizedResults = recognizedResultsByPaddleOcr
            // not grouping by result.Script and ImageId to remove duplicated text boxes across all scripts of an image
            .GroupBy(result => result.ImageId).SelectMany(g => g.DistinctBy(result => result.TextBox));
        var detectedTextBoxes = (
            from detectionResult in await _requester.RequestForDetection(imagesKeyByUrlFilename, stoppingToken)
            join recognitionResult in uniqueRecognizedResults on detectionResult.ImageId equals recognitionResult.ImageId
            let percentageOfIntersection = GetPercentageOfIntersectionArea(detectionResult.TextBox, recognitionResult.TextBox)
            select (detectionResult.ImageId, detectionBox: detectionResult.TextBox, recognitionBox: recognitionResult.TextBox, percentageOfIntersection)
            ).ToList();
        var recognizedDetectedTextBoxes = (
            from t in detectedTextBoxes
            group t by t.recognitionBox into g
            select g.Where(t => t.percentageOfIntersection > 90)
                .DefaultIfEmpty().MaxBy(t => t.percentageOfIntersection) into t
            where t != default
            select t
            ).ToLookup(t => t.ImageId);
        var unrecognizedDetectedTextBoxes = (
            from t in detectedTextBoxes
            group t by t.recognitionBox into g
            select g.Where(t => t.percentageOfIntersection < 10)
                .DefaultIfEmpty().MinBy(t => t.percentageOfIntersection) into t
            where t != default
            select t
            ).ToLookup(t => t.ImageId);
        var recognizedResultsByTesseract = recognizedResultsByPaddleOcr
            .Where(result => result.Confidence < _paddleOcrConfidenceThreshold)
            .GroupBy(result => (result.ImageId, result.Script))
            .Select(g =>
            {
                var imageId = g.Key.ImageId;
                var boxes = g.Select(result =>
                        recognizedDetectedTextBoxes[imageId].FirstOrDefault(t => t.recognitionBox == result.TextBox).detectionBox)
                    // ReSharper disable once RedundantEnumerableCastCall
                    .OfType<PaddleOcrResponse.TextBox>()
                    .Concat(unrecognizedDetectedTextBoxes[imageId].Select(t => t.detectionBox))
                    .Distinct();
                return TesseractRecognizer.PreprocessTextBoxes(imageId, imagesKeyByUrlFilename[imageId], g.Key.Script, boxes);
            })
            .SelectMany(textBoxes =>
                textBoxes.SelectMany(_recognizer.RecognizePreprocessedTextBox))
            .Select(result =>
            {
                var (imageId, script, _, textBox, text, confidence) = result;
                return new PaddleOcrRequester.RecognitionResult(imageId, script, textBox, text, confidence);
            })
            .ToList();
        foreach (var groupByImageId in recognizedResultsByPaddleOcr
                     .Where(result => result.Confidence >= _paddleOcrConfidenceThreshold)
                     .Concat(recognizedResultsByTesseract)
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
                                    $"{result.TextBox.ToCircumscribedRectangle()} {result.Confidence} {result.Text.Trim()}"))
                            .Trim()))
                        .Trim()
                        .Normalize(NormalizationForm.FormKC); // https://unicode.org/reports/tr15/
                    _logger.LogInformation("\n{}", texts);
                });
        }
        Environment.Exit(0);
    }
}
