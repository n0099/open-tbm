namespace tbm.ImagePipeline.Consumer;

public abstract class MatrixConsumer
{
    public void Consume(ImagePipelineDbContext db, Dictionary<ImageId, Mat> matricesKeyByImageId, CancellationToken stoppingToken)
    {
        // defensive clone to prevent any consumer mutate the original matrix in param
        var clonedMatricesKeyByImageId = matricesKeyByImageId.ToDictionary(pair => pair.Key, pair => pair.Value.Clone());
        try
        {
            ConsumeInternal(db, clonedMatricesKeyByImageId, stoppingToken);
        }
        finally
        {
            clonedMatricesKeyByImageId.Values.ForEach(mat => mat.Dispose());
        }
    }

    protected abstract void ConsumeInternal
        (ImagePipelineDbContext db, Dictionary<ImageId, Mat> matricesKeyByImageId, CancellationToken stoppingToken);
}
