namespace tbm.ImagePipeline.Consumer;

public class FailedImageHandler(ILogger<FailedImageHandler> logger, CancellationToken stoppingToken)
{
    private readonly List<ImageFailed> _failedImages = new();

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
            var imageId = imageIdSelector(item);
            logger.LogError(e, "Exception for image {}: ", imageId);
            _failedImages.Add(new() {ImageId = imageId, Exception = e.ToString()});
            return imageId;
        }
    };

    public void SaveFailedImages(ImagePipelineDbContext db) => db.ImageFailed.AddRange(_failedImages);
}
