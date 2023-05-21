using System.Data;
using System.Threading.Channels;

namespace tbm.ImagePipeline;

public class ImagePipelineWorker : ErrorableWorker
{
    private readonly ILogger<ImagePipelineWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private readonly ChannelReader<ImageAndBytesKeyByImageId> _reader;

    public ImagePipelineWorker
        (ILogger<ImagePipelineWorker> logger, ILifetimeScope scope0, Channel<ImageAndBytesKeyByImageId> channel) :
        base(logger) => (_logger, _scope0, _reader) = (logger, scope0, channel);

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await foreach (var imageAndBytesKeyById in _reader.ReadAllAsync(stoppingToken))
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()("");
            await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);

            MetadataConsumer.Consume(db, imageAndBytesKeyById, stoppingToken);
            var matricesKeyByImageId = imageAndBytesKeyById.ToDictionary(pair => pair.Key,
                // preserve alpha channel if there's any, so the type of mat might be CV_8UC3 or CV_8UC4
                pair => Cv2.ImDecode(pair.Value.Bytes, ImreadModes.Unchanged));
            try
            {
                var hashConsumer = scope1.Resolve<HashConsumer>();
                hashConsumer.Consume(db, matricesKeyByImageId, stoppingToken);
                _ = await db.SaveChangesAsync(stoppingToken);
                await transaction.CommitAsync(stoppingToken);
                await ConsumeOcrConsumerWithAllScrips(scope1, matricesKeyByImageId, stoppingToken);
            }
            finally
            {
                matricesKeyByImageId.Values.ForEach(mat => mat.Dispose());
            }
        }
    }

    private static async Task ConsumeOcrConsumerWithAllScrips
        (ILifetimeScope scope, Dictionary<uint, Mat> matricesKeyByImageId, CancellationToken stoppingToken)
    {
        foreach (var script in new[] {"zh-Hans", "zh-Hant", "ja", "en"})
        {
            await using var scope1 = scope.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()(script);
            await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);

            var ocrConsumer = scope1.Resolve<OcrConsumer.New>()(script);
            await ocrConsumer.InitializePaddleOcr(stoppingToken);
            ocrConsumer.Consume(db, matricesKeyByImageId, stoppingToken);

            _ = await db.SaveChangesAsync(stoppingToken);
            await transaction.CommitAsync(stoppingToken);
        }
    }
}
