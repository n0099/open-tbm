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
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        ImageId lastImageIdInPreviousBatch = 0;
        while (await _writer.WaitToWriteAsync(stoppingToken))
        {
            var images = GetUnconsumedImages(lastImageIdInPreviousBatch);
            if (images.Any()) await _writer.WriteAsync(new(
                await Task.WhenAll(images.Select(async image =>
                    new ImageWithBytes(image, await _imageRequester.GetImageBytes(image, stoppingToken))
                ))), stoppingToken);
            else break; // no more images to consume
            lastImageIdInPreviousBatch = images.Last().ImageId;
        }
        _writer.Complete();
    }

    private List<ImageInReply> GetUnconsumedImages(ImageId lastImageIdInPreviousBatch)
    { // dispose db inside scope1 after returned to prevent long running idle connection
        using var scope1 = _scope0.BeginLifetimeScope();
        var db = scope1.Resolve<ImagePipelineDbContext.New>()("");
        return (from image in db.ImageInReplies.AsNoTracking()
                where image.ImageId > lastImageIdInPreviousBatch
                      // only entity ImageMetadata is one-to-zeroOrOne mapping with entity ImageInReply
                      && !db.ImageMetadata.Select(e => e.ImageId).Contains(image.ImageId)
                orderby image.ImageId
                select image)
            .Take(_batchSize * _interlaceBatchCount)
            .Where(image => image.ImageId % _interlaceBatchCount == _interlaceBatchIndex)
            .ToList();
    }
}
