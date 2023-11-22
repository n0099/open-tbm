namespace tbm.ImagePipeline;

public class ImageBatchProducingWorker(
        ILogger<ImageBatchProducingWorker> logger,
        IConfiguration config,
        ImageRequester imageRequester,
        Channel<List<ImageWithBytes>> channel,
        Func<Owned<ImagePipelineDbContext.NewDefault>> dbContextDefaultFactory)
    : ErrorableWorker(shouldExitOnException: true)
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
            using var dbFactory = dbContextDefaultFactory();
            lastImageIdInPreviousBatch = dbFactory.Value()
                .ImageMetadata.AsNoTracking().Max(image => image.ImageId);
        }
        while (true)
        {
            // dispose the scope of Owned<DbContext> after yield to prevent long-life idle connection
            using var dbFactory = dbContextDefaultFactory();
            var db = dbFactory.Value();
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
