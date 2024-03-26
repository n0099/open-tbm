namespace tbm.ImagePipeline.Consumer;

public abstract class MatrixConsumer : IConsumer<ImageKeyWithMatrix>
{
    public (IEnumerable<ImageId> Failed, IEnumerable<ImageId> Consumed) Consume(
        ImagePipelineDbContext db,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithT,
        CancellationToken stoppingToken = default)
    {
        // defensive clone to prevent any consumer mutate the original matrix given in param
        var clonedImageKeysWithMatrix =
            imageKeysWithT.Select(i => i with {Matrix = i.Matrix.Clone()}).ToList();
        try
        {
            var failed = ConsumeInternal(db, clonedImageKeysWithMatrix, stoppingToken).ToList();
            return (failed, clonedImageKeysWithMatrix.Select(i => i.ImageId).Except(failed));
        }
        finally
        {
            clonedImageKeysWithMatrix.ForEach(i => i.Matrix.Dispose());
        }
    }

    protected abstract IEnumerable<ImageId> ConsumeInternal(
        ImagePipelineDbContext db,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default);
}
