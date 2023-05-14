using System.Data;

namespace tbm.ImagePipeline.Consumer;

public abstract class MatrixConsumer
{
    private readonly ImagePipelineDbContext.New _dbContextFactory;
    private readonly string _script;

    protected MatrixConsumer(ImagePipelineDbContext.New dbContextFactory, string script = "")
    {
        _dbContextFactory = dbContextFactory;
        _script = script;
    }

    public async Task Consume(Dictionary<ImageId, Mat> matricesKeyByImageId, CancellationToken stoppingToken)
    {
        // defensive clone to prevent any consumer mutate the original matrix in param
        var clonedMatricesKeyByImageId = matricesKeyByImageId.ToDictionary(pair => pair.Key, pair => pair.Value.Clone());
        try
        {
            var db = _dbContextFactory(_script);
            await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);
            ConsumeInternal(db, clonedMatricesKeyByImageId, stoppingToken);
            _ = await db.SaveChangesAsync(stoppingToken);
            await transaction.CommitAsync(stoppingToken);
        }
        finally
        {
            clonedMatricesKeyByImageId.Values.ForEach(mat => mat.Dispose());
        }
    }

    protected abstract void ConsumeInternal
        (ImagePipelineDbContext db, Dictionary<ImageId, Mat> matricesKeyByImageId, CancellationToken stoppingToken);
}
