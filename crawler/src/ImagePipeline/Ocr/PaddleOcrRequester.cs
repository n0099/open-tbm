using System.Net.Http.Json;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class PaddleOcrRequester
{
    private static HttpClient _http = null!;
    private readonly string _paddleOcrDetectionEndpoint;

    public PaddleOcrRequester(IConfiguration config, IHttpClientFactory httpFactory)
    {
        _http = httpFactory.CreateClient("tbImage");
        var configSection = config.GetSection("ImageOcrPipeline");
        var paddleOcrServingEndpoint = configSection.GetValue("PaddleOcrServingEndpoint", "") ?? "";
        _paddleOcrDetectionEndpoint = paddleOcrServingEndpoint + "/predict/ocr_det";
    }

    public record TextBoxAndDegrees(PaddleOcrResponse.TextBox TextBox, float RotationDegrees);

    public record DetectionResult(string ImageId, byte[] ImageBytes, IEnumerable<TextBoxAndDegrees> TextBoxes);

    public Task<IEnumerable<DetectionResult>> RequestForDetection
        (Dictionary<string, byte[]> imagesKeyById, CancellationToken stoppingToken = default) =>
        Request(_paddleOcrDetectionEndpoint, imagesKeyById, t =>
            new DetectionResult(t.ImageId, t.ImageBytes, t.Results
                .Select(i => new TextBoxAndDegrees(i.TextBox!, i.TextBox!.GetRotationDegrees()))), stoppingToken);

    public record RecognitionResult(string ImageId, string Text, float Confidence);

    public static Task<IEnumerable<IEnumerable<RecognitionResult>>> RequestForRecognition
        (string endpoint, Dictionary<string, byte[]> imagesKeyById, CancellationToken stoppingToken = default) =>
        Request(endpoint, imagesKeyById,
            t => t.Results.Select(result =>
                new RecognitionResult(t.ImageId, result.Text!, result.Confidence!.Value)),
            stoppingToken);

    private static async Task<IEnumerable<T>>
        Request<T>(string endpoint, Dictionary<string, byte[]> imagesKeyById,
            Func<(string ImageId, byte[] ImageBytes, PaddleOcrResponse.Result[] Results), T> responseTransformer,
            CancellationToken stoppingToken = default) where T : class
    {
        if (!imagesKeyById.Values.Any()) return Array.Empty<T>();
        var requestPayload = new PaddleOcrRequestPayload(imagesKeyById.Values.Select(Convert.ToBase64String));
        var httpResponse = await _http.PostAsJsonAsync(endpoint, requestPayload, stoppingToken);
        var response = await httpResponse.Content.ReadFromJsonAsync
            <PaddleOcrResponse>(PaddleOcrResponse.JsonSerializerOptions, stoppingToken);
        if (!httpResponse.IsSuccessStatusCode
            || response?.Results == null
            || response.Msg != ""
            || response.Status != "000")
            throw new($"{endpoint} responded with non zero status or empty msg"
                      + $"raw={await httpResponse.Content.ReadAsStringAsync(stoppingToken)}");
        return imagesKeyById.Zip(response.Results, (pair, results) => (pair.Key, pair.Value, results)).Select(responseTransformer);
    }
}
