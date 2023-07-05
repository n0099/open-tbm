namespace tbm.ImagePipeline;

public class ImageBatchProducingWorker : ErrorableWorker
{
    private readonly ILogger<ImageBatchProducingWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private readonly ImageRequester _imageRequester;
    private readonly ChannelWriter<List<ImageWithBytes>> _writer;
    private readonly int _produceImageBatchSize;
    private readonly int _prefetchUnconsumedImagesFactor;
    private readonly int _interlaceBatchCount;
    private readonly int _interlaceBatchIndex;
    private readonly bool _startFromLatestSuccessful;

    public ImageBatchProducingWorker(
        ILogger<ImageBatchProducingWorker> logger,
        IHostApplicationLifetime applicationLifetime,
        IConfiguration config,
        ILifetimeScope scope0, ImageRequester imageRequester,
        Channel<List<ImageWithBytes>> channel
    ) : base(logger, applicationLifetime, shouldExitOnException: true)
    {
        (_logger, _scope0, _imageRequester, _writer) = (logger, scope0, imageRequester, channel);
        var configSection = config.GetSection("ImageBatchProducer");
        _produceImageBatchSize = configSection.GetValue("ProduceImageBatchSize", 16);
        _prefetchUnconsumedImagesFactor = configSection.GetValue("PrefetchUnconsumedImagesFactor", 16);
        _interlaceBatchCount = configSection.GetValue("InterlaceBatchCount", 1);
        _interlaceBatchIndex = configSection.GetValue("InterlaceBatchIndex", 0);
        _startFromLatestSuccessful = configSection.GetValue("StartFromLatestSuccessful", false);
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        foreach (var imageBatch in GetUnconsumedImages()
                     .SelectMany(imageBatch => imageBatch.Chunk(_produceImageBatchSize)))
        {
            var imagesWithBytes = await Task.WhenAll(imageBatch.Select(async image =>
            {
                try
                {
                    return new ImageWithBytes(image, await _imageRequester.GetImageBytes(image, stoppingToken));
                }
                catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
                {
                    throw;
                }
                catch (Exception e)
                {
                    _logger.LogError(e, "Exception");
                    return null;
                }
            }));
            await _writer.WriteAsync(new(imagesWithBytes.OfType<ImageWithBytes>()), stoppingToken);
        }
        _writer.Complete();
        _logger.LogInformation("No more image batch to consume, configure \"ImageBatchProducer.StartFromLatestSuccessful\""
                               + " to false then restart will rerun all previous failed image batches from start");
    }

    private IEnumerable<IEnumerable<ImageInReply>> GetUnconsumedImages()
    {
        ImageId lastImageIdInPreviousBatch = 0;
        if (_startFromLatestSuccessful)
        {
            using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.NewDefault>()();
            lastImageIdInPreviousBatch = db.ImageMetadata.AsNoTracking().Max(image => image.ImageId);
        }
        while (true)
        {
            // dispose db inside scope1 after returned to prevent long running idle connection
            using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.NewDefault>()();
            var interlaceBatches = (
                    from image in db.ImageInReplies.AsNoTracking()
                    where image.ImageId > lastImageIdInPreviousBatch
                    where _startFromLatestSuccessful
                        // fast path compare to WHERE imageId NOT IN (SELECT imageId FROM ...)
                        ? image.ImageId > db.ImageMetadata.Max(e => e.ImageId)
                        // only entity ImageMetadata is one-to-zeroOrOne mapping with entity ImageInReply
                        : !db.ImageMetadata.Select(e => e.ImageId).Contains(image.ImageId)
                    orderby image.ImageId
                    select image)
                .Take(_produceImageBatchSize * _prefetchUnconsumedImagesFactor * _interlaceBatchCount).ToList();
            if (!interlaceBatches.Any()) yield break;
            lastImageIdInPreviousBatch = interlaceBatches.Last().ImageId;
            yield return interlaceBatches
                .Where(image => image.ImageId % _interlaceBatchCount == _interlaceBatchIndex);
        }
    }
}
