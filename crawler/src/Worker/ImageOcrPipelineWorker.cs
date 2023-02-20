using System.Text.RegularExpressions;
using Emgu.CV;
using tbm.Crawler.ImagePipeline.Ocr;

namespace tbm.Crawler.Worker;

public partial class ImageOcrPipelineWorker : BackgroundService
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private static HttpClient _http = null!;
    private readonly PaddleOcrRequester _requester;
    private readonly TextRecognizer _recognizer;
    private readonly float _aspectRatioThresholdToUseTesseract;
    private readonly int _gridSizeToMergeBoxesIntoSingleLine;

    [GeneratedRegex("^[0-9]+x[0-9]+@([0-9]+),([0-9]+)$", RegexOptions.Compiled, 100)]
    private static partial Regex ExtractTextBoxBoundaryGeneratedRegex();
    private static readonly Regex ExtractTextBoxBoundaryRegex = ExtractTextBoxBoundaryGeneratedRegex();

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IConfiguration config,
        IHttpClientFactory httpFactory, PaddleOcrRequester requester, TextRecognizer recognizer)
    {
        _logger = logger;
        _http = httpFactory.CreateClient("tbImage");
        _requester = requester;
        _recognizer = recognizer;
        var configSection = config.GetSection("ImageOcrPipeline");
        _aspectRatioThresholdToUseTesseract = configSection.GetValue("AspectRatioThresholdToUseTesseract", 0.8f);
        _gridSizeToMergeBoxesIntoSingleLine = configSection.GetValue("GridSizeToMergeBoxesIntoSingleLine", 10);
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        var imagesUrlFilename = new List<string> {""};
        var imagesKeyByUrlFilename = (await Task.WhenAll(
                imagesUrlFilename.Select(async filename =>
                    (filename, bytes: await _http.GetByteArrayAsync(filename + ".jpg", stoppingToken)))))
            .ToDictionary(t => t.filename, t => t.bytes);
        var processedImagesTextBoxes =
            (await _requester.RequestForDetection(imagesKeyByUrlFilename, stoppingToken))
            .Select(TextBoxPreprocessor.GetTextBoxesProcessor()).ToList();
        var reprocessedImagesTextBoxes = (await Task.WhenAll(
                from imageAndProcessedTextBoxes in processedImagesTextBoxes
                let boxImagesBytesKeyByBoundary =
                    imageAndProcessedTextBoxes.ProcessedTextBoxes
                        // rerun detect and process for cropped images of text boxes with non-zero rotation degrees
                        .Where(b => b.RotationDegrees != 0)
                        .ToDictionary(b => b.TextBoxBoundary,
                            b => CvInvoke.Imencode(".png", b.ProcessedTextBoxMat))
                let requestTask = _requester.RequestForDetection(boxImagesBytesKeyByBoundary, stoppingToken)
                select requestTask))
            .Select(results => results.Select(TextBoxPreprocessor.GetTextBoxesProcessor(true)));
        var mergedTextBoxesPerImage = from t in
                processedImagesTextBoxes.Zip(reprocessedImagesTextBoxes)
            let textBoxes = t.First.ProcessedTextBoxes
                .Where(b => b.RotationDegrees == 0)
                .Concat(t.Second.SelectMany(imageAndProcessedTextBoxes => imageAndProcessedTextBoxes.ProcessedTextBoxes))
            select (t.First.ImageId, textBoxes);
        var recognizedImages = await Task.WhenAll(mergedTextBoxesPerImage.Select(async t =>
        {
            var boxesUsingTesseractToRecognize = t.textBoxes.Where(b =>
                (float)b.ProcessedTextBoxMat.Width / b.ProcessedTextBoxMat.Height < _aspectRatioThresholdToUseTesseract).ToList();
            var boxesUsingPaddleOcrToRecognize = t.textBoxes
                .ExceptBy(boxesUsingTesseractToRecognize.Select(b => b.TextBoxBoundary), b => b.TextBoxBoundary);
            return new
            {
                t.ImageId,
                Texts = boxesUsingTesseractToRecognize
                    .SelectMany(_recognizer.RecognizeViaTesseract)
                    .Concat((await _recognizer.RecognizeViaPaddleOcr(boxesUsingPaddleOcrToRecognize, stoppingToken))
                        .SelectMany(i => i))
            };
        }));
        foreach (var imageIdAndTexts in recognizedImages)
        {
            imageIdAndTexts.Texts.GroupBy(box =>
                {
                    var vertSuffixIndex = box.Script.IndexOf("_vert", StringComparison.Ordinal);
                    return vertSuffixIndex == -1 ? box.Script : box.Script[..vertSuffixIndex];
                })
                .SelectMany(scripts => scripts.Select(result =>
                        {
                            var match = ExtractTextBoxBoundaryRegex.Match(result.TextBoxBoundary);
                            var exceptionMessage = $"Failed to parse TextBoxBoundary with value {result.TextBoxBoundary}";
                            if (!match.Success) throw new(exceptionMessage);
                            var topLeftPoint = match.Groups.Values.Skip(1)
                                .Select(g => int.TryParse(g.ValueSpan, out var parsed)
                                    ? parsed
                                    : throw new(exceptionMessage))
                                .ToList();
                            // align to a virtual grid to prevent a single line that splitting into multiple text boxes
                            // which have similar but different values of y coordinates get rearranged in a wrong order
                            var alignedY = (int)Math.Round((double)topLeftPoint[1] / _gridSizeToMergeBoxesIntoSingleLine);
                            return (result, X: topLeftPoint[0], Y: alignedY);
                        })
                        .OrderBy(t => t.Y).ThenBy(t => t.X)
                        .GroupBy(t => t.Y, t => t.result),
                    (scripts, lines) => (script: scripts.Key, lines))
                .GroupBy(t => t.script, t => t.lines)
                .ForEach(groupByScript =>
                {
                    _logger.LogInformation("{} {}", imageIdAndTexts.ImageId, groupByScript.Key);
                    var texts = string.Join("\n", groupByScript.Select(groupByLine =>
                            string.Join(" ", groupByLine.Select(i => i.TextBoxBoundary + " " + i.Text.Trim()))
                            .Trim()))
                        .Trim()
                        .Normalize(NormalizationForm.FormKC); // https://unicode.org/reports/tr15/
                    _logger.LogInformation("\n{}", texts);
                });
        }
        _logger.LogInformation("{}", JsonSerializer.Serialize(recognizedImages));
        Environment.Exit(0);
    }
}
