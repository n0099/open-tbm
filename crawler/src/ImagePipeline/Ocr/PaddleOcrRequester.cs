using System.Drawing;
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

    public record DetectionResult(string ImageId, byte[] ImageBytes,
        IEnumerable<(PaddleOcrResponse.TextBox TextBox, float RotationDegrees)> TextBoxes);

    public Task<IEnumerable<DetectionResult>> RequestForDetection
        (Dictionary<string, byte[]> imagesKeyById, CancellationToken stoppingToken = default) =>
        Request(_paddleOcrDetectionEndpoint, imagesKeyById,
            nestedResults =>
                from t in imagesKeyById
                    .Zip(nestedResults, (pair, results) => (ImageId: pair.Key, ImageBytes: pair.Value, results))
                let boxes = t.results
                    .Select(result => (result.TextBox!, result.TextBox!.GetRotationDegrees()))
                select new DetectionResult(t.ImageId, t.ImageBytes, boxes),
            stoppingToken);

    public record RecognitionResult(Rectangle TextBoxBoundary, string Text, float Confidence);

    public static Task<IEnumerable<RecognitionResult>> RequestForRecognition
        (string endpoint, Dictionary<Rectangle, byte[]> imagesKeyById, CancellationToken stoppingToken = default) =>
        Request(endpoint, imagesKeyById,
            nestedResults => imagesKeyById
                // nested results array in the recognition response should contain only one element which includes all results for each image
                .Zip(nestedResults[0], (pair, result) => (ImageId: pair.Key, result))
                .Select(t => new RecognitionResult(t.ImageId, t.result.Text!, t.result.Confidence!.Value)),
            stoppingToken);

    private static async Task<IEnumerable<TReturn>> Request<TReturn, TImageKey>(string endpoint,
        Dictionary<TImageKey, byte[]> imagesKeyById,
        Func<PaddleOcrResponse.Result[][], IEnumerable<TReturn>> resultSelector,
        CancellationToken stoppingToken = default)
        where TReturn : class where TImageKey : notnull
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
