using System.Net.Http.Json;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class PaddleOcrRequester
{
    private static HttpClient _http = null!;
    private readonly Dictionary<string, string> _paddleOcrRecognitionEndpointsKeyByScript;
    private readonly float _paddleOcrConfidenceThreshold;

    public PaddleOcrRequester(IConfiguration config, IHttpClientFactory httpFactory)
    {
        _http = httpFactory.CreateClient("tbImage");
        var configSection = config.GetSection("ImageOcrPipeline");
        var paddleOcrServingEndpoint = configSection.GetValue("PaddleOcrServingEndpoint", "") ?? "";
        _paddleOcrRecognitionEndpointsKeyByScript = new()
        {
            {"zh-Hans", paddleOcrServingEndpoint + "/predict/ocr_system"},
            {"zh-Hant", paddleOcrServingEndpoint + "/predict/ocr_system_zh-Hant"},
            {"ja", paddleOcrServingEndpoint + "/predict/ocr_system_ja"},
            {"en", paddleOcrServingEndpoint + "/predict/ocr_system_en"}
        };
        _paddleOcrConfidenceThreshold = configSection.GetValue("PaddleOcrConfidenceThreshold", 80f);
    }

    public record RecognitionResult(string ImageId, string Script, PaddleOcrResponse.TextBox TextBox, string Text, float Confidence);

    public Task<IEnumerable<RecognitionResult>[]> RequestForRecognition
        (Dictionary<string, byte[]> imagesBytesKeyById, CancellationToken stoppingToken = default) =>
        Task.WhenAll(_paddleOcrRecognitionEndpointsKeyByScript.Select(pair =>
            Request(pair.Value, imagesBytesKeyById,
                nestedResults => imagesBytesKeyById
                    .Zip(nestedResults, (images, results) => (imageId: images.Key, results))
                    .SelectMany(t => t.results
                        .Where(result => result.Confidence > _paddleOcrConfidenceThreshold / 100)
                        .Select(result => new RecognitionResult(t.imageId, pair.Key, result.TextBox, result.Text, result.Confidence))),
                stoppingToken)));

    private static async Task<IEnumerable<TReturn>> Request<TReturn, TImageKey>(string endpoint,
        Dictionary<TImageKey, byte[]> imagesKeyById,
        Func<PaddleOcrResponse.Result[][], IEnumerable<TReturn>> resultSelector,
        CancellationToken stoppingToken = default)
        where TImageKey : notnull
    {
        if (!imagesKeyById.Values.Any()) return Array.Empty<TReturn>();
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
