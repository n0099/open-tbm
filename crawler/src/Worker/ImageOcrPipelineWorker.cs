using System.Net.Http.Json;
using System.Text.Json.Serialization;
using SixLabors.ImageSharp;
using SixLabors.ImageSharp.Processing;

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
        var imagesKeyByUrlFilename = (await Task.WhenAll(
                imagesUrlFilename.Select(async filename =>
                    (filename, bytes: await _http.GetByteArrayAsync(filename + ".jpg", stoppingToken)))))
            .ToDictionary(t => t.filename, t => t.bytes);
        var textBoxesKeyByUrlFilename = await RequestPaddleOcrForDetection(imagesKeyByUrlFilename, stoppingToken);
        imagesKeyByUrlFilename
            .Join(textBoxesKeyByUrlFilename, pair => pair.Key, t => t.UrlFilename,
                (pair, tuple) => (UrlFilename: pair.Key, ImageBytes: pair.Value, tuple.BoxesAndAngles))
            .ForEach(t =>
            {
                using var img = Image.Load(t.ImageBytes, out var format);
                t.BoxesAndAngles.ForEach(t2 =>
                {
                    if (t2.Angle == 0) return;
                    img.Clone(i => i.Crop(t2.TextBox.ToCircumscribedRectangle()).Rotate(t2.Angle))
                        .SaveAsPng($@"{t2.Angle}{t2.TextBox.ToCircumscribedRectangle()}.png");
                });
            });
    }

    private async Task<IEnumerable<(string UrlFilename, IEnumerable<(PaddleOcrDetectionResponse.TextBox TextBox, float Angle)> BoxesAndAngles)>>
        RequestPaddleOcrForDetection(Dictionary<string, byte[]> imagesKeyByUrlFilename, CancellationToken stoppingToken)
    {
        var requestPayload = new PaddleOcrRequestPayload(imagesKeyByUrlFilename.Values.Select(Convert.ToBase64String));
        var response = await _http.PostAsJsonAsync(_paddleOcrDetectionEndpoint, requestPayload, stoppingToken);
        var detectionResponse = await response.Content.ReadFromJsonAsync<PaddleOcrDetectionResponse>
            (PaddleOcrDetectionResponse.JsonSerializerOptions, stoppingToken);
        if (detectionResponse is not {Msg: ""} || detectionResponse.Status != "000")
            throw new("PaddleOcrEndpoint/predict/ocr_det responded with non zero status or empty msg"
                      + $"raw={await response.Content.ReadAsStringAsync(stoppingToken)}");
        return imagesKeyByUrlFilename.Keys.Zip(detectionResponse.Results)
            .Select(t => (t.First, t.Second.Select(i => (i.TextBox, i.TextBox.FindRotationAngle()))));
    }

    private record PaddleOcrRequestPayload(IEnumerable<string> Images);

    private record PaddleOcrDetectionResponse(string Msg, PaddleOcrDetectionResponse.Result[][] Results, string Status)
    {
        public static readonly JsonSerializerOptions JsonSerializerOptions = new() { PropertyNamingPolicy = JsonNamingPolicy.CamelCase};

        public record Result(TextBox TextBox)
        {
            [JsonPropertyName("text_region")]
            public TextBox TextBox { get; } = TextBox;
        }

        [JsonConverter(typeof(TextBoxJsonConverter))]
        public record TextBox(Coordinate TopLeft, Coordinate TopRight, Coordinate BottomLeft, Coordinate BottomRight)
        {
            // https://www.mathopenref.com/coordbounds.html
            public Rectangle ToCircumscribedRectangle() => Rectangle.FromLTRB(
                Math.Min(TopLeft.X, BottomLeft.X),
                Math.Min(TopLeft.Y, TopRight.Y), // we are in left-handed cartesian coordinate system so using Min for the topmost point on Y axis
                Math.Max(TopRight.X, BottomRight.X),
                Math.Max(BottomLeft.Y, BottomRight.Y));

            public float FindRotationAngle()
            {
                if (TopLeft.X == BottomLeft.X
                    && TopRight.X == BottomRight.X
                    && TopLeft.Y == TopRight.Y
                    && BottomLeft.Y == BottomRight.Y) return 0;
                var xAxisDiff = BottomLeft.X - TopLeft.X;
                var yAxisDiff = BottomLeft.Y - TopLeft.Y;
                return (float)Math.Atan2(xAxisDiff, yAxisDiff) * (180 / (float)Math.PI);
            }
        }

        public record Coordinate(int X, int Y);

        public class TextBoxJsonConverter : JsonConverter<TextBox>
        {
            private static Coordinate ReadCoordinate(ref Utf8JsonReader reader)
            {
                if (reader.TokenType != JsonTokenType.StartArray) throw new JsonException();
                int x = 0;
                int y = 0;
                var i = 0;
                while (reader.Read())
                {
                    if (reader.TokenType == JsonTokenType.Number)
                    {
                        if (i == 0) x = reader.GetInt32();
                        else if (i == 1) y = reader.GetInt32();
                        else throw new JsonException();
                    }
                    else if (reader.TokenType == JsonTokenType.EndArray) break;
                    else throw new JsonException();
                    i++;
                }
                return new(x, y);
            }

            public override TextBox Read(ref Utf8JsonReader reader, Type typeToConvert, JsonSerializerOptions options)
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

            public override void Write(Utf8JsonWriter writer, TextBox value, JsonSerializerOptions options) =>
                throw new NotImplementedException();
        }
    }
}
