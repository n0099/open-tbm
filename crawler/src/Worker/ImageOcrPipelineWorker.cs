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
        var recognizedResultsByPaddleOcr = (await _requester.RequestForRecognition(imagesKeyByUrlFilename, stoppingToken))
            .SelectMany(i => i).ToList();
        var recognizedResultsByTesseract = recognizedResultsByPaddleOcr
            .Where(result => result.Confidence < _paddleOcrConfidenceThreshold)
            .GroupBy(result => (result.ImageId, result.Script))
            .Select(g => TesseractRecognizer.PreprocessTextBoxes(
                g.Key.ImageId, imagesKeyByUrlFilename[g.Key.ImageId], g.Key.Script, g.Select(result => result.TextBox)))
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
                            var alignedY = (int)Math.Round((double)rect.Y / _gridSizeToMergeBoxesIntoSingleLine);
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
