namespace tbm.ImagePipeline;

public class ImageRequester
{
    private readonly ILogger<ImageRequester> _logger;
    private readonly IConfigurationSection _config;
    private readonly IHttpClientFactory _httpFactory;

    public ImageRequester(ILogger<ImageRequester> logger, IConfiguration config, IHttpClientFactory httpFactory)
    {
        (_logger, _httpFactory) = (logger, httpFactory);
        _config = config.GetSection("ImageRequester");
    }

    public async Task<byte[]> GetImageBytes(TiebaImage image, CancellationToken stoppingToken)
    {
        var urlFilename = image.UrlFilename;
        var expectedByteSize = image.ByteSize;
        var http = _httpFactory.CreateClient("tbImage");
        if (_config.GetValue("LogTrace", false))
            _logger.LogTrace("Requesting image {} and expecting {} bytes of file size", urlFilename, expectedByteSize);

        var response = await http.GetAsync(urlFilename + ".jpg", stoppingToken);
        var contentLength = response.Content.Headers.ContentLength;
        if (expectedByteSize != 0 && contentLength != expectedByteSize)
            throw new($"Unexpected response header Content-Length: {contentLength} bytes, expecting {expectedByteSize} bytes.");

        var bytes = await response.Content.ReadAsByteArrayAsync(stoppingToken);
        if (expectedByteSize != 0 && bytes.Length != expectedByteSize)
            throw new($"Unexpected response body length {bytes.Length} bytes, expecting {expectedByteSize} bytes.");
        return bytes;
    }
}
