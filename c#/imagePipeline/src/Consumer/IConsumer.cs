namespace tbm.ImagePipeline.Consumer;

public interface IConsumer<in T>
{
    public (IEnumerable<ImageId> Failed, IEnumerable<ImageId> Consumed) Consume(
        ImagePipelineDbContext db,
        IReadOnlyCollection<T> imageKeysWithT,
        CancellationToken stoppingToken = default);
}
