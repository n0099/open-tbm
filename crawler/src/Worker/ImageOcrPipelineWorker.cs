using System.Drawing;
using System.Net.Http.Json;
using System.Text.Json.Serialization;
using Emgu.CV;
using Emgu.CV.CvEnum;
using Emgu.CV.Structure;

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
        var processedTextBoxes = (await RequestPaddleOcrForDetection(imagesKeyByUrlFilename, stoppingToken)).Select(ProcessTextBoxes);
        var redetectedTextBoxes = await Task.WhenAll(
            processedTextBoxes.Select(i =>
            {
                var textBoxesKeyByIdAndBoundary = i.ProcessedTextBoxes
                    .Where(b => b.RotationDegrees != 0)
                    .ToDictionary(b => $"{i.ImageId} {b.TextBoxBoundary}", b => b.ProcessedImageBytes);
                return RequestPaddleOcrForDetection(textBoxesKeyByIdAndBoundary, stoppingToken);
            }));
        foreach (var d in redetectedTextBoxes.Select(i => i.Select(ProcessTextBoxes)))
        {
            d.ForEach(d2 => d2.ProcessedTextBoxes.ForEach(b =>
                File.WriteAllBytes($"{b.TextBoxBoundary}.png", b.ProcessedImageBytes)));
        }
    }

    private record ProcessedTextBox(string TextBoxBoundary, byte[] ProcessedImageBytes, float RotationDegrees);

    private record ImageAndProcessedTextBoxes(string ImageId, List<ProcessedTextBox> ProcessedTextBoxes);

    private static ImageAndProcessedTextBoxes ProcessTextBoxes(PaddleOcrDetectionResult detectionResult)
    {
        using var imageMat = new Mat();
        CvInvoke.Imdecode(detectionResult.ImageBytes, ImreadModes.Unchanged, imageMat);
        return new(detectionResult.ImageId, detectionResult.TextBoxes
            .Where(textBoxAndDegrees => textBoxAndDegrees.RotationDegrees != 0)
            .Select(textBoxAndDegrees =>
            {
                var (textBox, degrees) = textBoxAndDegrees;
                var circumscribed = textBox.ToCircumscribedRectangle();
                using var croppedMat = new Mat(imageMat, circumscribed);
                using var processedMat = degrees == 0 ? croppedMat : croppedMat.Rotate(degrees);
                var processedBytes = CvInvoke.Imencode(".png", processedMat);
                var rectangleBoundary = $"{circumscribed.Width}x{circumscribed.Height}@{circumscribed.X},{circumscribed.Y}";
                return new ProcessedTextBox(rectangleBoundary, processedBytes, degrees);
            })
            .ToList()); // opt-out lazy eval of IEnumerable since imageMat is already disposed after return
    }

    private record TextBoxAndDegrees(PaddleOcrDetectionResponse.TextBox TextBox, float RotationDegrees);

    private record PaddleOcrDetectionResult(string ImageId, byte[] ImageBytes, IEnumerable<TextBoxAndDegrees> TextBoxes);

    private async Task<IEnumerable<PaddleOcrDetectionResult>>
        RequestPaddleOcrForDetection(Dictionary<string, byte[]> imagesKeyById, CancellationToken stoppingToken = default)
    {
        var requestPayload = new PaddleOcrRequestPayload(imagesKeyById.Values.Select(Convert.ToBase64String));
        var response = await _http.PostAsJsonAsync(_paddleOcrDetectionEndpoint, requestPayload, stoppingToken);
        var detectionResponse = await response.Content.ReadFromJsonAsync<PaddleOcrDetectionResponse>
            (PaddleOcrDetectionResponse.JsonSerializerOptions, stoppingToken);
        if (!response.IsSuccessStatusCode
            || detectionResponse?.Results == null
            || detectionResponse.Msg != ""
            || detectionResponse.Status != "000")
            throw new("PaddleOcrEndpoint/predict/ocr_det responded with non zero status or empty msg"
                      + $"raw={await response.Content.ReadAsStringAsync(stoppingToken)}");
        return imagesKeyById.Zip(detectionResponse.Results)
            .Select(t => new PaddleOcrDetectionResult(t.First.Key, t.First.Value,
                t.Second.Select(i => new TextBoxAndDegrees(i.TextBox, i.TextBox.GetRotationDegrees()))));
    }

    private record PaddleOcrRequestPayload(IEnumerable<string> Images);

    private record PaddleOcrDetectionResponse(string Msg, PaddleOcrDetectionResponse.Result[][] Results, string Status)
    {
        private class ResultsConverter : JsonConverter<Result[][]>
        {
            public override Result[][]? Read(ref Utf8JsonReader reader, Type typeToConvert, JsonSerializerOptions options) =>
                reader.TokenType == JsonTokenType.String && reader.GetString() == ""
                    ? null
                    : JsonSerializer.Deserialize<Result[][]>(ref reader, options);

            public override void Write(Utf8JsonWriter writer, Result[][] value, JsonSerializerOptions options) => throw new NotImplementedException();
        }

        [JsonConverter(typeof(ResultsConverter))]
        public Result[][]? Results { get; } = Results;

        public static readonly JsonSerializerOptions JsonSerializerOptions = new() { PropertyNamingPolicy = JsonNamingPolicy.CamelCase};

        public record Result(TextBox TextBox)
        {
            [JsonPropertyName("text_region")]
            public TextBox TextBox { get; } = TextBox;
        }

        [JsonConverter(typeof(TextBoxJsonConverter))]
        public record TextBox(Coordinate TopLeft, Coordinate TopRight, Coordinate BottomLeft, Coordinate BottomRight)
        {
            public Rectangle ToCircumscribedRectangle() => Rectangle.FromLTRB(
                // https://www.mathopenref.com/coordbounds.html
                Math.Min(TopLeft.X, BottomLeft.X),
                Math.Min(TopLeft.Y, TopRight.Y), // in left-handed cartesian coordinate system the minimum point is the topmost point on Y axis
                Math.Max(TopRight.X, BottomRight.X),
                Math.Max(BottomLeft.Y, BottomRight.Y));

            public float GetRotationDegrees()
            {
                if (TopLeft.X == BottomLeft.X
                    && TopRight.X == BottomRight.X
                    && TopLeft.Y == TopRight.Y
                    && BottomLeft.Y == BottomRight.Y) return 0;
                var xAxisDiff = BottomLeft.X - TopLeft.X;
                var yAxisDiff = BottomLeft.Y - TopLeft.Y;
                // https://stackoverflow.com/questions/13002979/how-to-calculate-rotation-angle-from-rectangle-points
                // https://www.calculator.net/triangle-calculator.html?vc=&vx=4&vy=&va=90&vz=1&vb=&angleunits=d&x=53&y=29
                return (float)Math.Atan2(xAxisDiff, yAxisDiff) * (float)(180 / Math.PI); // radians to degrees
            }
        }

        public record Coordinate(int X, int Y);

        public class TextBoxJsonConverter : JsonConverter<TextBox>
        {
            private static Coordinate ReadCoordinate(ref Utf8JsonReader reader)
            {
                if (reader.TokenType != JsonTokenType.StartArray) throw new JsonException();
                var x = 0;
                var y = 0;
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

public static class MatExtension
{
    /// <summary>
    /// <see>https://stackoverflow.com/questions/22041699/rotate-an-image-without-cropping-in-opencv-in-c/75451191#75451191</see>
    /// </summary>
    public static Mat Rotate(this Mat src, float degrees)
    {
        degrees = -degrees; // counter-clockwise to clockwise
        var center = new PointF((src.Width - 1) / 2f, (src.Height - 1) / 2f);
        using var rotationMat = new Mat();
        CvInvoke.GetRotationMatrix2D(center, degrees, 1, rotationMat);
        var boundingRect = new RotatedRect(new(), src.Size, degrees).MinAreaRect();
        rotationMat.Set(0, 2, rotationMat.Get<double>(0, 2) + (boundingRect.Width / 2f) - (src.Width / 2f));
        rotationMat.Set(1, 2, rotationMat.Get<double>(1, 2) + (boundingRect.Height / 2f) - (src.Height / 2f));
        var rotatedSrc = new Mat();
        CvInvoke.WarpAffine(src, rotatedSrc, rotationMat, boundingRect.Size);
        return rotatedSrc;
    }

    /// <summary>
    /// <see>https://stackoverflow.com/questions/32255440/how-can-i-get-and-set-pixel-values-of-an-emgucv-mat-image/69537504#69537504</see>
    /// </summary>
    public static unsafe void Set<T>(this Mat mat, int row, int col, T value) =>
        _ = new Span<T>(mat.DataPointer.ToPointer(), mat.Rows * mat.Cols * mat.ElementSize)
        {
            [(row * mat.Cols) + col] = value
        };

    public static unsafe T Get<T>(this Mat mat, int row, int col) =>
        new ReadOnlySpan<T>(mat.DataPointer.ToPointer(), mat.Rows * mat.Cols * mat.ElementSize)
            [(row * mat.Cols) + col];

    public static unsafe ReadOnlySpan<T> Get<T>(this Mat mat, int row, System.Range cols)
    {
        var span = new ReadOnlySpan<T>(mat.DataPointer.ToPointer(), mat.Rows * mat.Cols * mat.ElementSize);
        var (offset, length) = cols.GetOffsetAndLength(span.Length);
        return span.Slice((row * mat.Cols) + offset, length);
    }
}
