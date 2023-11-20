namespace tbm.ImagePipeline.Consumer;

public abstract class MatrixConsumer : IConsumer<ImageKeyWithMatrix>
{
    public (IEnumerable<ImageId> Failed, IEnumerable<ImageId> Consumed) Consume(
        ImagePipelineDbContext db,
        IEnumerable<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default)
    {
        // defensive clone to prevent any consumer mutate the original matrix given in param
        var clonedImageKeysWithMatrix =
            imageKeysWithMatrix.Select(i => i with {Matrix = i.Matrix.Clone()}).ToList();
        try
        {
            return ConsumeInternal(db, clonedImageKeysWithMatrix, stoppingToken);
        }
        finally
        {
            clonedImageKeysWithMatrix.ForEach(i => i.Matrix.Dispose());
        }
    }

    protected abstract (IEnumerable<ImageId> Failed, IEnumerable<ImageId> Consumed) ConsumeInternal(
        ImagePipelineDbContext db,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default);
}
