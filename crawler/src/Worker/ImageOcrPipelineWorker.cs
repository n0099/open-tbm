using Emgu.CV;
using Emgu.CV.OCR;
using tbm.Crawler.ImagePipeline.Ocr;

namespace tbm.Crawler.Worker;

public class ImageOcrPipelineWorker : BackgroundService
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private readonly IConfigurationSection _config;
    private static HttpClient _http = null!;
    private readonly string _paddleOcrDetectionEndpoint;
    private readonly Dictionary<string, string> _paddleOcrRecognitionEndpointsKeyByScript;
    private readonly Dictionary<string, Tesseract> _tesseractInstancesKeyByScript;
    private readonly float _tesseractConfidenceThreshold;
    private readonly float _aspectRatioThresholdToUseTesseract;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IConfiguration config, IHttpClientFactory httpFactory)
    {
        _logger = logger;
        _config = config.GetSection("ImageOcrPipeline");
        _http = httpFactory.CreateClient("tbImage");

        var paddleOcrServingEndpoint = _config.GetValue("PaddleOcrServingEndpoint", "") ?? "";
        _paddleOcrDetectionEndpoint = paddleOcrServingEndpoint + "/predict/ocr_det";
        _paddleOcrRecognitionEndpointsKeyByScript = new()
        {
            {"zh-Hans", paddleOcrServingEndpoint + "/predict/ocr_rec"},
            {"zh-Hant", paddleOcrServingEndpoint + "/predict/ocr_rec_zh-Hant"},
            {"ja", paddleOcrServingEndpoint + "/predict/ocr_rec_ja"},
            {"en", paddleOcrServingEndpoint + "/predict/ocr_rec_en"},
        };

        var tesseractDataPath = _config.GetValue("TesseractDataPath", "") ?? "";
        Tesseract CreateTesseract(string scripts) =>
            new(tesseractDataPath, scripts, OcrEngineMode.LstmOnly)
            { // https://pyimagesearch.com/2021/11/15/tesseract-page-segmentation-modes-psms-explained-how-to-improve-your-ocr-accuracy/
                PageSegMode = PageSegMode.SingleBlockVertText
            };
        _tesseractInstancesKeyByScript = new()
        {
            {"zh-Hans_vert", CreateTesseract("best/chi_sim_vert")},
            {"zh-Hant_vert", CreateTesseract("best/chi_tra_vert")},
            {"ja_vert", CreateTesseract("best/jpn_vert")}
        };
        _tesseractConfidenceThreshold = _config.GetValue("TesseractConfidenceThreshold", 20f);
        _aspectRatioThresholdToUseTesseract = _config.GetValue("AspectRatioThresholdToUseTesseract", 0.8f);
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        var imagesUrlFilename = new List<string> {""};
        var imagesKeyByUrlFilename = (await Task.WhenAll(
                imagesUrlFilename.Select(async filename =>
                    (filename, bytes: await _http.GetByteArrayAsync(filename + ".jpg", stoppingToken)))))
            .ToDictionary(t => t.filename, t => t.bytes);
        var processedImagesTextBoxes = (await RequestPaddleOcrForDetection(imagesKeyByUrlFilename, stoppingToken))
            .Select(ProcessTextBoxes).ToList();
        var reprocessedImagesTextBoxes = (await Task.WhenAll(
                processedImagesTextBoxes.Select(i =>
                    RequestPaddleOcrForDetection(i.ProcessedTextBoxes
                        .Where(b => b.RotationDegrees != 0) // rerun detect and process for cropped images of text boxes with non-zero rotation degrees
                        .ToDictionary(b => b.TextBoxBoundary, b => CvInvoke.Imencode(".png", b.ProcessedTextBoxMat)), stoppingToken))))
            .Select(imageDetectionResults => imageDetectionResults.Select(ProcessTextBoxes));
        var mergedTextBoxesPerImage = processedImagesTextBoxes
            .Zip(reprocessedImagesTextBoxes)
            .Select(t => (t.First.ImageId,
                TextBoxes: t.First.ProcessedTextBoxes
                    .Where(b => b.RotationDegrees == 0)
                    .Concat(t.Second.SelectMany(i => i.ProcessedTextBoxes))));
        _logger.LogInformation("{}", JsonSerializer.Serialize(await Task.WhenAll(mergedTextBoxesPerImage.Select(async t =>
        {
            var boxesUsingTesseractToRecognize = t.TextBoxes.Where(b =>
                (float)b.ProcessedTextBoxMat.Width / b.ProcessedTextBoxMat.Height < _aspectRatioThresholdToUseTesseract).ToList();
            var boxesUsingPaddleOcrToRecognize = t.TextBoxes
                .ExceptBy(boxesUsingTesseractToRecognize.Select(b => b.TextBoxBoundary), b => b.TextBoxBoundary);
            return (t.ImageId, Texts: boxesUsingTesseractToRecognize
                .Select(RecognizeTextViaTesseract)
                .Concat(await RecognizeTextViaPaddleOcr(boxesUsingPaddleOcrToRecognize, stoppingToken)));
        }))));
    }
}
