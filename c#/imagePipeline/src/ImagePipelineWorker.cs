using System.Runtime.CompilerServices;

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

            await foreach (var imageAndBytesKeyById in ImageBatchGenerator(db, stoppingToken))
            {
                await metadataConsumer.Consume(imageAndBytesKeyById, stoppingToken);
                var matricesKeyByImageId = imageAndBytesKeyById.ToDictionary(pair => pair.Key,
                    // preserve alpha channel if there's any, so the type of mat might be CV_8UC3 or CV_8UC4
                    pair => Cv2.ImDecode(pair.Value.Bytes, ImreadModes.Unchanged));
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
        }
    }

    private async IAsyncEnumerable<Dictionary<ImageId, (TiebaImage Image, byte[] Bytes)>> ImageBatchGenerator
        (ImagePipelineDbContext db, [EnumeratorCancellation] CancellationToken stoppingToken)
    {
        ImageId lastImageIdInPreviousBatch = 0;
        while (true)
        {
            var images = (from image in db.Images.AsNoTracking()
                where image.ImageId > lastImageIdInPreviousBatch
                      && !db.ImageOcrBoxes.AsNoTracking().Select(e => e.ImageId).Contains(image.ImageId)
                      && !db.ImageOcrLines.AsNoTracking().Select(e => e.ImageId).Contains(image.ImageId)
                orderby image.ImageId
                select image).Take(_batchSize).ToList();
            if (images.Any()) yield return new(
                await Task.WhenAll(images.Select(async image =>
                    KeyValuePair.Create(image.ImageId, (image, await _imageRequester.GetImageBytes(image, stoppingToken))))));
            else yield break;
            lastImageIdInPreviousBatch = images.Last().ImageId;
        }
    }
}
