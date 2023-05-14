using OpenCvSharp.ImgHash;

namespace tbm.ImagePipeline.Consumer;

public class ImageHashConsumer : ImageBaseConsumer
{
    private readonly ILogger<ImageHashConsumer> _logger;

    public ImageHashConsumer(ILogger<ImageHashConsumer> logger,
        ImagePipelineDbContext.New dbContextFactory
    ) : base(dbContextFactory) => _logger = logger;

    protected override Task ConsumeInternal
        (ImagePipelineDbContext db, Dictionary<ImageId, Mat> matricesKeyByImageId, CancellationToken stoppingToken)
    {
        var hashAlgorithms = new List<ImgHashBase>
        {
            AverageHash.Create(), BlockMeanHash.Create(), MarrHildrethHash.Create(), PHash.Create()
        };
        hashAlgorithms.ForEach(hash =>
        {
            matricesKeyByImageId.ForEach(pair =>
            {
                using var mat = new Mat();
                hash.Compute(pair.Value, mat);
                _logger.LogInformation("{} {} {} {} {} {}",
                    pair.Key, hash.GetType().Name, mat.Height, mat.Width, mat.Channels(), mat.Dump());
            });
        });
        hashAlgorithms.ForEach(hash => hash.Dispose());
        return Task.CompletedTask;
    }
}
