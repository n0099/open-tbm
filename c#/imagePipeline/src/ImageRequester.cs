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
        var expectedBytesSize = image.BytesSize;
        var http = _httpFactory.CreateClient("tbImage");
        if (_config.GetValue("LogTrace", false))
            _logger.LogTrace("Requesting image {} and expecting {} bytes of file size",
                urlFilename, expectedBytesSize);

        var response = await http.GetAsync(urlFilename + ".jpg", stoppingToken);
        var contentLength = response.Content.Headers.ContentLength;
        if (expectedBytesSize != 0 && contentLength != expectedBytesSize)
            _logger.LogWarning("Unexpected response header Content-Length: {} bytes, expecting {} bytes for image {}",
                contentLength, expectedBytesSize, urlFilename);

        var bytes = await response.Content.ReadAsByteArrayAsync(stoppingToken);
        if (expectedBytesSize != 0 && bytes.Length != expectedBytesSize)
            _logger.LogWarning("Unexpected response body length {} bytes, expecting {} bytes for image {}",
                bytes.Length, expectedBytesSize, urlFilename);
        return bytes;
    }
}
