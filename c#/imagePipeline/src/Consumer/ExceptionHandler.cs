namespace tbm.ImagePipeline.Consumer;

public class ExceptionHandler(ILogger<ExceptionHandler> logger, CancellationToken stoppingToken)
{
    private Dictionary<ImageId, Exception> _exceptions = new();

    public Func<TSource, Either<TResult, ImageId>> Try<TSource, TResult>
        (Func<TSource, ImageId> imageIdSelector, Func<TSource, TResult> payload) => item =>
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
            var imageId = imageIdSelector(item);
            _exceptions.Add(imageId, e);
            return imageId;
        }
    };

    public void SaveFailedImages(DbContext db)
    {

    }
}
