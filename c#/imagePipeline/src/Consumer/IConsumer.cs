namespace tbm.ImagePipeline.Consumer;

public interface IConsumer<in T>
{
    public Option<IEnumerable<ImageId>> Consume(
        ImagePipelineDbContext db,
        IEnumerable<T> imageKeysWithMatrix,
        CancellationToken stoppingToken = default);
}
