using tbm.Crawler.ImagePipeline.Ocr;

namespace tbm.Crawler.Worker;

public class ImageOcrPipelineWorker : ErrorableWorker
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private static HttpClient _http = null!;
    private readonly PaddleOcrRequester _requester;
    private readonly int _gridSizeToMergeBoxesIntoSingleLine;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IConfiguration config,
        IHttpClientFactory httpFactory, PaddleOcrRequester requester) : base(logger)
    {
        _logger = logger;
        _http = httpFactory.CreateClient("tbImage");
        _requester = requester;
        var configSection = config.GetSection("ImageOcrPipeline");
        _gridSizeToMergeBoxesIntoSingleLine = configSection.GetValue("GridSizeToMergeBoxesIntoSingleLine", 10);
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        var imagesUrlFilename = new List<string> {""};
        var imagesKeyByUrlFilename = (await Task.WhenAll(
                imagesUrlFilename.Select(async filename =>
                    (filename, bytes: await _http.GetByteArrayAsync(filename + ".jpg", stoppingToken)))))
            .ToDictionary(t => t.filename, t => t.bytes);
        var recognizedImages = await _requester.RequestForRecognition(imagesKeyByUrlFilename, stoppingToken);
        foreach (var groupByImageId in recognizedImages.SelectMany(i => i).GroupBy(result => result.ImageId))
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
                                    $"{result.TextBox.ToCircumscribedRectangle()} {result.Confidence * 100:F0} {result.Text.Trim()}"))
                            .Trim()))
                        .Trim()
                        .Normalize(NormalizationForm.FormKC); // https://unicode.org/reports/tr15/
                    _logger.LogInformation("\n{}", texts);
                });
        }
        Environment.Exit(0);
    }
}
