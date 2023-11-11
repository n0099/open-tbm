namespace tbm.ImagePipeline.Consumer;

public class FailedImageHandler(ILogger<FailedImageHandler> logger, CancellationToken stoppingToken)
{
    private Dictionary<ImageId, Exception> _exceptions = new();

    public IEnumerable<Either<ImageId, TResult>> TrySelect<TSource, TResult>
        (IEnumerable<TSource> source, Func<TSource, ImageId> imageIdSelector, Func<TSource, TResult> payload) =>
        source.Select(Try(imageIdSelector, payload));

    public Func<TSource, Either<ImageId, TResult>> Try<TSource, TResult>
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
