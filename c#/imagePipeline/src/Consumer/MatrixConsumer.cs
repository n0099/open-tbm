namespace tbm.ImagePipeline.Consumer;

public abstract class MatrixConsumer
{
    public void Consume(
        ImagePipelineDbContext db,
        IEnumerable<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default)
    {
        // defensive clone to prevent any consumer mutate the original matrix in param
        var clonedImageKeysWithMatrix =
            imageKeysWithMatrix.Select(i => i with {Matrix = i.Matrix.Clone()}).ToList();
        try
        {
            ConsumeInternal(db, clonedImageKeysWithMatrix, stoppingToken);
        }
        finally
        {
            clonedImageKeysWithMatrix.ForEach(i => i.Matrix.Dispose());
        }
    }

    protected abstract void ConsumeInternal(
        ImagePipelineDbContext db,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default);
}
