using System.Threading.Channels;

namespace tbm.ImagePipeline;

public class ImageBatchProducingWorker : ErrorableWorker
{
    private readonly ILogger<ImageBatchProducingWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private readonly ImageRequester _imageRequester;
    private readonly ChannelWriter<List<ImageWithBytes>> _writer;
    private readonly int _batchSize;

    public ImageBatchProducingWorker(
        ILogger<ImageBatchProducingWorker> logger, IConfiguration config,
        ILifetimeScope scope0, ImageRequester imageRequester,
        Channel<List<ImageWithBytes>> channel) : base(logger)
    {
        (_logger, _scope0, _imageRequester, _writer) = (logger, scope0, imageRequester, channel);
        var configSection = config.GetSection("ImagePipeline");
        _batchSize = configSection.GetValue("BatchSize", 16);
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
            else break;
            lastImageIdInPreviousBatch = images.Last().ImageId;
        }
        _writer.Complete();
    }

    private List<TiebaImage> GetUnconsumedImages(ImageId lastImageIdInPreviousBatch)
    { // dispose db inside scope1 after returned to prevent long running db connection
        using var scope1 = _scope0.BeginLifetimeScope();
        var db = scope1.Resolve<ImagePipelineDbContext.New>()("");
        return (from image in db.Images.AsNoTracking()
                where image.ImageId > lastImageIdInPreviousBatch
                      // this will not get images that have not yet been consumed by OcrConsumer
                      // since the transaction in ConsumeOcrConsumerWithAllScrips() is de-synced with other consumer
                      && !db.ImageMetadata.Select(e => e.ImageId).Contains(image.ImageId)
                orderby image.ImageId
                select image
            ).Take(_batchSize).ToList();
    }
}
