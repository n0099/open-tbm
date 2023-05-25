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

    public async Task<byte[]> GetImageBytes(TiebaImage image, CancellationToken stoppingToken = default)
    {
        var urlFilename = image.UrlFilename;
        var expectedByteSize = image.ByteSize;
        var http = _httpFactory.CreateClient("tbImage");
        if (_config.GetValue("LogTrace", false))
            _logger.LogTrace("Requesting image {} and expecting {} bytes of file size",
                urlFilename, expectedByteSize);

        Context CreatePollyContext() => new() {{"ILogger<ImageRequester>", _logger}, {"imageUrlFilename", urlFilename}};
        Task<T> ExecuteByPolly<T>(Func<Task<T>> action) =>
            _registry.Get<IAsyncPolicy<T>>("tbImage")
                .ExecuteAsync(async (_, _) => await action(), CreatePollyContext(), stoppingToken);

        var response = await ExecuteByPolly(async () => await http.GetAsync(urlFilename + ".jpg", stoppingToken));
        var contentLength = response.Content.Headers.ContentLength;
        if (expectedByteSize != 0 && contentLength != expectedByteSize)
            _logger.LogWarning("Unexpected response header Content-Length: {} bytes, expecting {} bytes for image {}",
                contentLength, expectedByteSize, urlFilename);

        var bytes = await ExecuteByPolly(async () => await response.Content.ReadAsByteArrayAsync(stoppingToken));
        if (expectedByteSize != 0 && bytes.Length != expectedByteSize)
            _logger.LogWarning("Unexpected response body length {} bytes, expecting {} bytes for image {}",
                bytes.Length, expectedByteSize, urlFilename);
        return bytes;
    }
}
