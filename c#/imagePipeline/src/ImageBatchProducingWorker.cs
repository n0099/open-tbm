using System.Threading.Channels;

namespace tbm.ImagePipeline;

public class ImageBatchProducingWorker : ErrorableWorker
{
    private readonly ILogger<ImageBatchProducingWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private readonly ImageRequester _imageRequester;
    private readonly ChannelWriter<List<ImageWithBytes>> _writer;
    private readonly int _batchSize;
    private readonly int _interlaceBatchCount;
    private readonly int _interlaceBatchIndex;
    private readonly bool _rerunFailedBatchFromStart;

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
        _batchSize = configSection.GetValue("BatchSize", 16);
        _interlaceBatchCount = configSection.GetValue("InterlaceBatchCount", 1);
        _interlaceBatchIndex = configSection.GetValue("InterlaceBatchIndex", 0);
        _rerunFailedBatchFromStart = configSection.GetValue("RerunFailedBatchFromStart", false);
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        while (await _writer.WaitToWriteAsync(stoppingToken))
        {
            foreach (var images in GetUnconsumedImages())
            {
                var imageWithBytesArray = await Task.WhenAll(images.Select(async image =>
                {
                    try
                    {
                        return new ImageWithBytes(image, await _imageRequester.GetImageBytes(image, stoppingToken));
                    }
                    catch (Exception e)
                    {
                        _logger.LogError(e, "Exception");
                        return null;
                    }
                }));
                await _writer.WriteAsync(new(imageWithBytesArray.OfType<ImageWithBytes>()), stoppingToken);
            }
        }
        _writer.Complete();
        _logger.LogInformation("No more image batch to consume, configure \"ImageBatchProducer.RerunFailedBatchFromStart\""
                               + " to TRUE then restart will rerun all previous failed image batches from start.");
    }

    private IEnumerable<IEnumerable<ImageInReply>> GetUnconsumedImages()
    {
        ImageId lastImageIdInPreviousBatch = 0;
        if (!_rerunFailedBatchFromStart)
        {
            using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()("");
            lastImageIdInPreviousBatch = db.ImageMetadata.AsNoTracking().Max(image => image.ImageId);
        }
        while (true)
        {
            // dispose db inside scope1 after returned to prevent long running idle connection
            using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()("");
            var interlaceBatches = (
                    from image in db.ImageInReplies.AsNoTracking()
                    where image.ImageId > lastImageIdInPreviousBatch
                          // only entity ImageMetadata is one-to-zeroOrOne mapping with entity ImageInReply
                          && !db.ImageMetadata.Select(e => e.ImageId).Contains(image.ImageId)
                    orderby image.ImageId
                    select image)
                .Take(_batchSize * _interlaceBatchCount).ToList();
            if (!interlaceBatches.Any()) yield break;
            lastImageIdInPreviousBatch = interlaceBatches.Last().ImageId;
            yield return interlaceBatches
                .Where(image => image.ImageId % _interlaceBatchCount == _interlaceBatchIndex);
        }
    }
}
