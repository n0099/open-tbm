namespace tbm.ImagePipeline;

public class ImageRequester(
    ILogger<ImageRequester> logger,
    IConfiguration config,
    IHttpClientFactory httpFactory,
    IReadOnlyPolicyRegistry<string> registry,
    FixedWindowRateLimiter rateLimiter)
{
    private readonly IConfigurationSection _config = config.GetSection("ImageRequester");

    public async Task<byte[]> GetImageBytes(ImageInReply imageInReply, CancellationToken stoppingToken = default)
    {
        using var lease = await rateLimiter.AcquireAsync(permitCount: 0, stoppingToken);
        var imageId = imageInReply.ImageId;
        var urlFilename = imageInReply.UrlFilename;
        var expectedByteSize = imageInReply.ExpectedByteSize;
        if (_config.GetValue("LogTrace", false))
        {
            if (expectedByteSize == 0)
            {
                logger.LogTrace("Requesting image {} with id {} and not expecting determined byte size",
                    urlFilename, imageId);
            }
            else
            {
                logger.LogTrace("Requesting image {} with id {} and expecting {} bytes of file size",
                    urlFilename, imageId, expectedByteSize);
            }
        }

        Context CreatePollyContext() => new()
            {{"ILogger<ImageRequester>", logger}, {"imageUrlFilename", urlFilename}};
        async Task<T> ExecuteByPolly<T>(Func<Task<T>> action) =>
            await registry.Get<IAsyncPolicy<T>>($"tbImage<{typeof(T).Name}>")
                .ExecuteAsync(async (_, _) => await action(), CreatePollyContext(), stoppingToken);

#pragma warning disable IDISP001 // Dispose created
        var http = httpFactory.CreateClient("tbImage");
#pragma warning restore IDISP001 // Dispose created
        var response = await ExecuteByPolly(async () =>
            (await http.GetAsync(urlFilename + ".jpg", stoppingToken)).EnsureSuccessStatusCode());
        var contentLength = response.Content.Headers.ContentLength;
        var bytes = await ExecuteByPolly(async () => await response.Content.ReadAsByteArrayAsync(stoppingToken));

        if (contentLength != bytes.Length)
            throw new InvalidDataException($"Mismatch response body length {bytes.Length} with the value {contentLength}"
                                           + $" in the Content-Length header for image {urlFilename} with id {imageId}.");
        if (expectedByteSize == 0) return bytes;
        if (contentLength != expectedByteSize)
        {
            logger.LogWarning(
                "Unexpected response header Content-Length: {} bytes, expecting {} bytes for image {} with id {}",
                contentLength, expectedByteSize, urlFilename, imageId);
        }
        else if (bytes.Length != expectedByteSize)
        {
            logger.LogWarning(
                "Unexpected response body length {} bytes, expecting {} bytes for image {} with id {}",
                bytes.Length, expectedByteSize, urlFilename, imageId);
        }

        return bytes;
    }
}
