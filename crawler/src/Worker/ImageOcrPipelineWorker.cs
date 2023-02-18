using Emgu.CV;
using tbm.Crawler.ImagePipeline.Ocr;

namespace tbm.Crawler.Worker;

public class ImageOcrPipelineWorker : BackgroundService
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private static HttpClient _http = null!;
    private readonly float _aspectRatioThresholdToUseTesseract;
    private readonly PaddleOcrRequester _requester;
    private readonly TextRecognizer _recognizer;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IConfiguration config,
        IHttpClientFactory httpFactory, PaddleOcrRequester requester, TextRecognizer recognizer)
    {
        _logger = logger;
        _http = httpFactory.CreateClient("tbImage");
        _requester = requester;
        _recognizer = recognizer;
        var configSection = config.GetSection("ImageOcrPipeline");
        _aspectRatioThresholdToUseTesseract = configSection.GetValue("AspectRatioThresholdToUseTesseract", 0.8f);
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        var imagesUrlFilename = new List<string> {""};
        var imagesKeyByUrlFilename = (await Task.WhenAll(
                imagesUrlFilename.Select(async filename =>
                    (filename, bytes: await _http.GetByteArrayAsync(filename + ".jpg", stoppingToken)))))
            .ToDictionary(t => t.filename, t => t.bytes);
        var processedImagesTextBoxes = (await _requester.RequestForDetection(imagesKeyByUrlFilename, stoppingToken))
            .Select(TextBoxPreprocessor.ProcessTextBoxes).ToList();
        var reprocessedImagesTextBoxes = (await Task.WhenAll(
                processedImagesTextBoxes.Select(imageAndProcessedTextBoxes =>
                    _requester.RequestForDetection(imageAndProcessedTextBoxes.ProcessedTextBoxes
                        .Where(b => b.RotationDegrees != 0) // rerun detect and process for cropped images of text boxes with non-zero rotation degrees
                        .ToDictionary(b => b.TextBoxBoundary, b => CvInvoke.Imencode(".png", b.ProcessedTextBoxMat)), stoppingToken))))
            .Select(imageDetectionResults => imageDetectionResults.Select(TextBoxPreprocessor.ProcessTextBoxes));
        var mergedTextBoxesPerImage = processedImagesTextBoxes
            .Zip(reprocessedImagesTextBoxes)
            .Select(t => (t.First.ImageId,
                TextBoxes: t.First.ProcessedTextBoxes
                    .Where(b => b.RotationDegrees == 0)
                    .Concat(t.Second.SelectMany(imageAndProcessedTextBoxes => imageAndProcessedTextBoxes.ProcessedTextBoxes))));
        _logger.LogInformation("{}", JsonSerializer.Serialize(await Task.WhenAll(mergedTextBoxesPerImage.Select(async t =>
        {
            var boxesUsingTesseractToRecognize = t.TextBoxes.Where(b =>
                (float)b.ProcessedTextBoxMat.Width / b.ProcessedTextBoxMat.Height < _aspectRatioThresholdToUseTesseract).ToList();
            var boxesUsingPaddleOcrToRecognize = t.TextBoxes
                .ExceptBy(boxesUsingTesseractToRecognize.Select(b => b.TextBoxBoundary), b => b.TextBoxBoundary);
            return new
            {
                t.ImageId,
                Texts = boxesUsingTesseractToRecognize
                    .SelectMany(_recognizer.RecognizeViaTesseract)
                    .Concat((await _recognizer.RecognizeViaPaddleOcr(boxesUsingPaddleOcrToRecognize, stoppingToken))
                        .SelectMany(i => i))
            };
        }))));
        Environment.Exit(0);
    }
}
