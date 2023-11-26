namespace tbm.ImagePipeline.Consumer;

public interface IConsumer<in T>
{
    public (IEnumerable<ImageId> Failed, IEnumerable<ImageId> Consumed) Consume(
        ImagePipelineDbContext db,
        IReadOnlyCollection<T> imageKeysWithMatrix,
        CancellationToken stoppingToken = default);
}
