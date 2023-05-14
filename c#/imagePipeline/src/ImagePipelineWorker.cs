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
        (_logger, _scope0, _imageRequester) = (logger, scope0, imageRequester);
        var configSection = config.GetSection("ImagePipeline");
        _batchSize = configSection.GetValue("BatchSize", 16);
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        foreach (var script in new[] {"zh-Hans", "zh-Hant", "ja", "en"})
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()(script);
            var metadataConsumer = scope1.Resolve<MetadataConsumer>();
            var hashConsumer = scope1.Resolve<HashConsumer>();
            var ocrConsumer = scope1.Resolve<OcrConsumer.New>()(script);
            await ocrConsumer.InitializePaddleOcr(stoppingToken);

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
                    var imagesBytesKeyById = new Dictionary<ImageId, byte[]>(
                        await Task.WhenAll(images.Select(async image =>
                            KeyValuePair.Create(image.ImageId, await _imageRequester.GetImageBytes(image, stoppingToken)))));
                    await metadataConsumer.Consume(imagesBytesKeyById, stoppingToken);
                    var matricesKeyByImageId = imagesBytesKeyById.ToDictionary(pair => pair.Key,
                        pair => Cv2.ImDecode(pair.Value, ImreadModes.Color)); // convert to BGR three channels without alpha
                    try
                    {
                        await hashConsumer.Consume(matricesKeyByImageId, stoppingToken);
                        await ocrConsumer.Consume(matricesKeyByImageId, stoppingToken);
                    }
                    finally
                    {
                        matricesKeyByImageId.Values.ForEach(mat => mat.Dispose());
                    }
                }
                lastImageIdInPreviousBatch = images.Last().ImageId;
            }
        }
    }
}
