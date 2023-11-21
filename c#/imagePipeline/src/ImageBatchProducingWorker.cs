namespace tbm.ImagePipeline;

public class ImageBatchProducingWorker(
    ILogger<ImageBatchProducingWorker> logger,
    IHostApplicationLifetime applicationLifetime,
    IConfiguration config,
    ILifetimeScope scope0, ImageRequester imageRequester,
    Channel<List<ImageWithBytes>> channel
) : ErrorableWorker(logger, applicationLifetime, shouldExitOnException: true)
{
    private readonly IConfigurationSection _config = config.GetSection("ImageBatchProducer");
    private int ProduceImageBatchSize => _config.GetValue("ProduceImageBatchSize", 16);
    private int PrefetchUnconsumedImagesFactor => _config.GetValue("PrefetchUnconsumedImagesFactor", 16);
    private int InterlaceBatchCount => _config.GetValue("InterlaceBatchCount", 1);
    private int InterlaceBatchIndex => _config.GetValue("InterlaceBatchIndex", 0);
    private bool StartFromLatestSuccessful => _config.GetValue("StartFromLatestSuccessful", true);
    private bool AllowPartiallyConsumed => _config.GetValue("AllowPartiallyConsumed", false);

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        var writer = channel.Writer;
        foreach (var imageBatch in GetUnconsumedImages()
                     .SelectMany(imageBatch => imageBatch.Chunk(ProduceImageBatchSize)))
        {
            var imagesWithBytes = await Task.WhenAll(imageBatch.Select(async image =>
            {
                try
                {
                    return new ImageWithBytes(image, await imageRequester.GetImageBytes(image, stoppingToken));
                }
                catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
                {
                    throw;
                }
                catch (Exception e)
                {
                    logger.LogError(e, "Exception");
                    return null;
                }
            }));
            await writer.WriteAsync(new(imagesWithBytes.OfType<ImageWithBytes>()), stoppingToken);
        }
        writer.Complete();
    }

    private IEnumerable<IEnumerable<ImageInReply>> GetUnconsumedImages()
    {
        ImageId lastImageIdInPreviousBatch = 0;
        if (StartFromLatestSuccessful)
        {
            using var scope1 = scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.NewDefault>()();
            lastImageIdInPreviousBatch = db.ImageMetadata.AsNoTracking().Max(image => image.ImageId);
        }
        while (true)
        {
            // dispose db inside scope1 after returned to prevent long running idle connection
            using var scope1 = scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.NewDefault>()();
            var interlaceBatches = (
                    from i in db.ImageInReplies.AsNoTracking()
                    where i.ImageId > lastImageIdInPreviousBatch
                    where !StartFromLatestSuccessful
                          || i.ImageId > db.ImageMetadata.Max(e => e.ImageId)
                    where AllowPartiallyConsumed
                          || !(i.MetadataConsumed || i.HashConsumed || i.QrCodeConsumed || i.OcrConsumed)
                    orderby i.ImageId
                    select i)
                .Take(ProduceImageBatchSize * PrefetchUnconsumedImagesFactor * InterlaceBatchCount).ToList();
            if (!interlaceBatches.Any()) yield break;
            lastImageIdInPreviousBatch = interlaceBatches[^1].ImageId;
            yield return interlaceBatches
                .Where(image => image.ImageId % InterlaceBatchCount == InterlaceBatchIndex);
        }
    }
}
