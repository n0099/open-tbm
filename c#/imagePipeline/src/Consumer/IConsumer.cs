namespace tbm.ImagePipeline.Consumer;

public interface IConsumer<in T>
{
    public IEnumerable<ImageId> Consume(
        ImagePipelineDbContext db,
        IEnumerable<T> imageKeysWithMatrix,
        CancellationToken stoppingToken = default);
}
