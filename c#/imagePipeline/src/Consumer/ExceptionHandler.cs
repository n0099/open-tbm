namespace tbm.ImagePipeline.Consumer;

public class ExceptionHandler(ILogger<ExceptionHandler> logger, CancellationToken stoppingToken)
{
    private Dictionary<ImageId, Exception> _exceptions = new();

    public Func<TSource, Option<TResult>> TryWithData<TSource, TResult>
        (Func<TSource, ImageId> imageKeySelector, Func<TSource, TResult> payload) => item =>
    {
        try
        {
            return payload(item);
        }
        catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
        {
            throw;
        }
        catch (Exception e)
        {
            logger.LogError(e, "Exception: ");
            _exceptions.Add(imageKeySelector(item), e);
            return Prelude.None;
        }
    };

    public void SaveFailedImages(DbContext db)
    {

    }
}
