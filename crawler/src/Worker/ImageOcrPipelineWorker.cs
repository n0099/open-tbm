using System.Net.Http.Json;
using System.Text.Json.Serialization;

namespace tbm.Crawler.Worker;

public class ImageOcrPipelineWorker : BackgroundService
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private readonly IConfigurationSection _config;
    private static HttpClient _http = null!;
    private readonly string _paddleOcrDetectionEndpoint;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IConfiguration config, IHttpClientFactory httpFactory)
    {
        _logger = logger;
        _config = config.GetSection("ImageOcrPipeline");
        _http = httpFactory.CreateClient("tbImage");
        _paddleOcrDetectionEndpoint = (_config.GetValue("PaddleOcrEndpoint", "") ?? "") + "/predict/ocr_det";
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        var imagesUrlFilename = new List<string> {""};
        var textRegionsKeyByUrlFilename = await RequestPaddleOcrForDetection((
                await Task.WhenAll(imagesUrlFilename
                    .Select(async filename =>
                        KeyValuePair.Create(filename, Convert.ToBase64String(
                            await _http.GetByteArrayAsync(filename + ".jpg", stoppingToken))))))
            .ToDictionary(i => i.Key, i => i.Value), stoppingToken);
    }

    private async Task<Dictionary<string, IEnumerable<PaddleOcrDetectionResponse.TextRegion>>>
        RequestPaddleOcrForDetection(Dictionary<string, string> base64EncodedImagesKeyByUrlFilename, CancellationToken stoppingToken)
    {
        var requestPayload = new PaddleOcrRequestPayload(base64EncodedImagesKeyByUrlFilename.Values);
        var response = await _http.PostAsJsonAsync(_paddleOcrDetectionEndpoint, requestPayload, stoppingToken);
        var detectionResponse = await response.Content.ReadFromJsonAsync<PaddleOcrDetectionResponse>
            (PaddleOcrDetectionResponse.JsonSerializerOptions, stoppingToken);
        if (detectionResponse is not {Msg: ""} || detectionResponse.Status != "000")
            throw new("PaddleOcrEndpoint/predict/ocr_det responded with non zero status or empty msg"
                      + $"raw={await response.Content.ReadAsStringAsync(stoppingToken)}");
        return base64EncodedImagesKeyByUrlFilename.Keys.Zip(detectionResponse.Results)
            .ToDictionary(t => t.First, t => t.Second.Select(r => r.TextRegion));
    }

    private record PaddleOcrRequestPayload(IEnumerable<string> Images);

    private record PaddleOcrDetectionResponse(string Msg, PaddleOcrDetectionResponse.Result[][] Results, string Status)
    {
        public static readonly JsonSerializerOptions JsonSerializerOptions = new() { PropertyNamingPolicy = JsonNamingPolicy.CamelCase};

        public record Result(TextRegion TextRegion)
        {
            [JsonPropertyName("text_region")]
            public TextRegion TextRegion { get; } = TextRegion;
        }

        [JsonConverter(typeof(TextRegionJsonConverter))]
        public record TextRegion(Coordinate TopLeft, Coordinate TopRight, Coordinate BottomLeft, Coordinate BottomRight);

        public record Coordinate(uint X, uint Y);

        public class TextRegionJsonConverter : JsonConverter<TextRegion>
        {
            private static Coordinate ReadCoordinate(ref Utf8JsonReader reader)
            {
                if (reader.TokenType != JsonTokenType.StartArray) throw new JsonException();
                uint x = 0;
                uint y = 0;
                var i = 0;
                while (reader.Read())
                {
                    if (reader.TokenType == JsonTokenType.Number)
                    {
                        if (i == 0) x = reader.GetUInt32();
                        else if (i == 1) y = reader.GetUInt32();
                        else throw new JsonException();
                    }
                    else if (reader.TokenType == JsonTokenType.EndArray) break;
                    else throw new JsonException();
                    i++;
                }
                return new(x, y);
            }

            public override TextRegion Read(ref Utf8JsonReader reader, Type typeToConvert, JsonSerializerOptions options)
            {
                if (reader.TokenType != JsonTokenType.StartArray) throw new JsonException();
                var i = 0;
                var coordinates = new Coordinate[4];
                while (reader.Read())
                {
                    if (reader.TokenType == JsonTokenType.StartArray)
                    {
                        coordinates[i] = ReadCoordinate(ref reader);
                        if (i > 3) throw new JsonException();
                    }
                    else if (reader.TokenType == JsonTokenType.EndArray) break;
                    else throw new JsonException();
                    i++;
                }
                return new(coordinates[0], coordinates[1], coordinates[3], coordinates[2]);
            }

            public override void Write(Utf8JsonWriter writer, TextRegion value, JsonSerializerOptions options) =>
                throw new NotImplementedException();
        }
    }
}
