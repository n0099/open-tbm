using System.Threading.RateLimiting;
using Polly;
using Polly.Registry;

namespace tbm.ImagePipeline;

public class ImageRequester
{
    private readonly ILogger<ImageRequester> _logger;
    private readonly IConfigurationSection _config;
    private readonly IHttpClientFactory _httpFactory;
    private readonly IReadOnlyPolicyRegistry<string> _registry;

    public ImageRequester(
        ILogger<ImageRequester> logger, IConfiguration config,
        IHttpClientFactory httpFactory, IReadOnlyPolicyRegistry<string> registry)
    {
        (_logger, _httpFactory, _registry) = (logger, httpFactory, registry);
        _config = config.GetSection("ImageRequester");
    }

    public async Task<byte[]> GetImageBytes(ImageInReply imageInReply, CancellationToken stoppingToken = default)
    {
        var rateLimiter = new FixedWindowRateLimiter(new()
        {
            PermitLimit = _config.GetValue("LimitRps", 100),
            QueueLimit = _config.GetValue("LimitRps", 100),
            Window = TimeSpan.FromSeconds(1)
        });
        using var lease = await rateLimiter.AcquireAsync(permitCount: 0, stoppingToken);

        var imageId = imageInReply.ImageId;
        var urlFilename = imageInReply.UrlFilename;
        var expectedByteSize = imageInReply.ExpectedByteSize;
        if (_config.GetValue("LogTrace", false))
        {
            if (expectedByteSize == 0)
                _logger.LogTrace("Requesting image {} with id {} and not expecting determined byte size",
                    urlFilename, imageId);
            else
                _logger.LogTrace("Requesting image {} with id {} and expecting {} bytes of file size",
                    urlFilename, imageId, expectedByteSize);
        }

        Context CreatePollyContext() => new() {{"ILogger<ImageRequester>", _logger}, {"imageUrlFilename", urlFilename}};
        Task<T> ExecuteByPolly<T>(Func<Task<T>> action) =>
            _registry.Get<IAsyncPolicy<T>>($"tbImage<{typeof(T).Name}>")
                .ExecuteAsync(async (_, _) => await action(), CreatePollyContext(), stoppingToken);

        var http = _httpFactory.CreateClient("tbImage");
        var response = await ExecuteByPolly(async () => await http.GetAsync(urlFilename + ".jpg", stoppingToken));
        var contentLength = response.Content.Headers.ContentLength;
        if (expectedByteSize != 0 && contentLength != expectedByteSize) _logger.LogWarning(
            "Unexpected response header Content-Length: {} bytes, expecting {} bytes for image {} with id {}",
            contentLength, expectedByteSize, urlFilename, imageId);

        var bytes = await ExecuteByPolly(async () => await response.Content.ReadAsByteArrayAsync(stoppingToken));
        if (contentLength != bytes.Length)
            throw new($"Mismatch response body length {bytes.Length} with the value {contentLength} "
                      + $"in the Content-Length header for image {urlFilename} with id {imageId}.");
        if (expectedByteSize != 0 && bytes.Length != expectedByteSize) _logger.LogWarning(
            "Unexpected response body length {} bytes, expecting {} bytes for image {} with id {}",
            bytes.Length, expectedByteSize, urlFilename, imageId);

        return bytes;
    }
}
