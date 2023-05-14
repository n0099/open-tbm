using OpenCvSharp.ImgHash;

namespace tbm.ImagePipeline.Consumer;

public class HashConsumer : MatrixConsumer
{
    private readonly ILogger<HashConsumer> _logger;

    public HashConsumer(ILogger<HashConsumer> logger,
        ImagePipelineDbContext.New dbContextFactory
    ) : base(dbContextFactory) => _logger = logger;

    protected override void ConsumeInternal
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
    }
}
