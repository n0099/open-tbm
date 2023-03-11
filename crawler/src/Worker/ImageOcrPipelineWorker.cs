using OpenCvSharp;
using tbm.Crawler.ImagePipeline.Ocr;

namespace tbm.Crawler.Worker;

public class ImageOcrPipelineWorker : ErrorableWorker
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private static HttpClient _http = null!;
    private readonly ImageOcrConsumer.New _imageOcrConsumerFactory;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IHttpClientFactory httpFactory,
        ImageOcrConsumer.New imageOcrConsumerFactory) : base(logger)
    {
        _logger = logger;
        _http = httpFactory.CreateClient("tbImage");
        _imageOcrConsumerFactory = imageOcrConsumerFactory;
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
        var consumer = _imageOcrConsumerFactory("");
        await consumer.InitializePaddleOcrModel(stoppingToken);
        _logger.LogInformation("{}",
            consumer.GetRecognizedTextLinesKeyByImageId(imagesKeyByUrlFilename));
    }
}
