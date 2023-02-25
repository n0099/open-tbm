using System.Net.Http.Json;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class PaddleOcrRequester
{
    private static HttpClient _http = null!;
    private readonly string _detectionEndpoint;
    private readonly Dictionary<string, string> _recognitionEndpointsKeyByScript;

    public PaddleOcrRequester(IConfiguration config, IHttpClientFactory httpFactory)
    {
        _http = httpFactory.CreateClient("tbImage");
        var configSection = config.GetSection("ImageOcrPipeline").GetSection("PaddleOcr");
        var servingEndpoint = configSection.GetValue("ServingEndpoint", "") ?? "";
        _detectionEndpoint = servingEndpoint + "/predict/ocr_det";
        _recognitionEndpointsKeyByScript = new()
        {
            {"zh-Hans", servingEndpoint + "/predict/ocr_system"},
            {"zh-Hant", servingEndpoint + "/predict/ocr_system_zh-Hant"},
            {"ja", servingEndpoint + "/predict/ocr_system_ja"},
            {"en", servingEndpoint + "/predict/ocr_system_en"}
        };
    }

    public record DetectionResult(string ImageId, PaddleOcrResponse.TextBox TextBox);

    public Task<IEnumerable<DetectionResult>> RequestForDetection
        (Dictionary<string, byte[]> imagesBytesKeyById, CancellationToken stoppingToken = default) =>
        Request(_detectionEndpoint, imagesBytesKeyById,
            nestedResults => imagesBytesKeyById
                .Zip(nestedResults, (images, results) => (imageId: images.Key, results))
                .SelectMany(t => t.results
                    .Select(result => new DetectionResult(t.imageId, result.TextBox))),
            stoppingToken);

    public Task<IEnumerable<PaddleOcrRecognitionResult>[]> RequestForRecognition
        (Dictionary<string, byte[]> imagesBytesKeyById, CancellationToken stoppingToken = default) =>
        Task.WhenAll(_recognitionEndpointsKeyByScript.Select(pair =>
            Request(pair.Value, imagesBytesKeyById,
                nestedResults => imagesBytesKeyById
                    .Zip(nestedResults, (images, results) => (imageId: images.Key, results))
                    .SelectMany(t => t.results
                        .Select(result =>
                        {
                            var (textBox, text, confidence) = result;
                            var confidencePercentage = (confidence * 100).RoundToUshort();
                            return new PaddleOcrRecognitionResult(t.imageId, pair.Key, textBox, text, confidencePercentage);
                        })),
                stoppingToken)));

    private record PaddleOcrRequestPayload(IEnumerable<string> Images);

    private static async Task<IEnumerable<TResult>> Request<TResult, TImageKey>(string endpoint,
        Dictionary<TImageKey, byte[]> imagesKeyById,
        Func<PaddleOcrResponse.Result[][], IEnumerable<TResult>> resultSelector,
        CancellationToken stoppingToken = default)
        where TImageKey : notnull
    {
        if (!imagesKeyById.Values.Any()) return Array.Empty<TResult>();
        var requestPayload = new PaddleOcrRequestPayload(imagesKeyById.Values.Select(Convert.ToBase64String));
        var httpResponse = await _http.PostAsJsonAsync(endpoint, requestPayload, stoppingToken);
        var response = await httpResponse.Content.ReadFromJsonAsync
            <PaddleOcrResponse>(PaddleOcrResponse.JsonSerializerOptions, stoppingToken);
        if (!httpResponse.IsSuccessStatusCode
            || response?.NestedResults == null
            || response.Msg != ""
            || response.Status != "000")
            throw new($"{endpoint} responded with non zero status or empty msg"
                      + $"raw={await httpResponse.Content.ReadAsStringAsync(stoppingToken)}");
        return resultSelector(response.NestedResults);
    }
}
