namespace tbm.ImagePipeline.Consumer;

public class ExceptionHandler(ILogger<ExceptionHandler> logger, CancellationToken stoppingToken)
{
    private Dictionary<ImageId, Exception> _exceptions = new();

    public Func<TIn, Option<TOut>> TryWithData<TIn, TOut>
        (Func<TIn, ImageId> imageKeySelector, Func<TIn, TOut> payload) => item =>
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
