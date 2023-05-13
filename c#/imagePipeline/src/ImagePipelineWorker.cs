namespace tbm.ImagePipeline;

public class ImagePipelineWorker : ErrorableWorker
{
    private readonly ILogger<ImagePipelineWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private readonly ImageRequester _imageRequester;
    private readonly int _batchSize;

    public ImagePipelineWorker(ILogger<ImagePipelineWorker> logger, IConfiguration config,
        ILifetimeScope scope0, ImageRequester imageRequester) : base(logger)
    {
        _logger = logger;
        _scope0 = scope0;
        _imageRequester = imageRequester;
        var configSection = config.GetSection("ImageOcrPipeline");
        _batchSize = configSection.GetValue("BatchSize", 1000);
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        foreach (var script in new[] {"zh-Hans", "zh-Hant", "ja", "en"})
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()(script);
            var consumer = scope1.Resolve<ImageOcrConsumer.New>()(script);
            await consumer.InitializePaddleOcr(stoppingToken);
            ImageId lastImageIdInPreviousBatch = 0;
            var isNoMoreImages = false;
            while (!isNoMoreImages)
            {
                var images = (from image in db.Images.AsNoTracking()
                    where image.ImageId > lastImageIdInPreviousBatch
                          && !db.ImageOcrBoxes.AsNoTracking().Select(e => e.ImageId).Contains(image.ImageId)
                          && !db.ImageOcrLines.AsNoTracking().Select(e => e.ImageId).Contains(image.ImageId)
                    orderby image.ImageId
                    select image).Take(_batchSize).ToList();
                if (!images.Any()) isNoMoreImages = true;
                else
                {
                    var matricesKeyByImageId = (await Task.WhenAll(images.Select(async image =>
                            (image.ImageId, bytes: await _imageRequester.GetImageBytes(image, stoppingToken)))))
                        .ToDictionary(t => t.ImageId, t =>
                            Cv2.ImDecode(t.bytes, ImreadModes.Color)); // convert to BGR three channels without alpha
                    await consumer.RecognizeScriptsInImages(matricesKeyByImageId, stoppingToken);
                }
                lastImageIdInPreviousBatch = images.Last().ImageId;
            }
        }
    }
}
