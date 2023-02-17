using System.Drawing;
using System.Net.Http.Json;
using System.Text.Json.Serialization;
using Emgu.CV;
using Emgu.CV.CvEnum;
using Emgu.CV.OCR;
using Emgu.CV.Structure;
using Emgu.CV.Util;

namespace tbm.Crawler.Worker;

public class ImageOcrPipelineWorker : BackgroundService
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private readonly IConfigurationSection _config;
    private static HttpClient _http = null!;
    private readonly string _paddleOcrDetectionEndpoint;
    private readonly Dictionary<string, string> _paddleOcrRecognitionEndpointsKeyByScript;
    private readonly Dictionary<string, Tesseract> _tesseractInstancesKeyByScript;
    private readonly float _tesseractConfidenceThreshold;
    private readonly float _aspectRatioThresholdToUseTesseract;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IConfiguration config, IHttpClientFactory httpFactory)
    {
        _logger = logger;
        _config = config.GetSection("ImageOcrPipeline");
        _http = httpFactory.CreateClient("tbImage");

        var paddleOcrServingEndpoint = _config.GetValue("PaddleOcrServingEndpoint", "") ?? "";
        _paddleOcrDetectionEndpoint = paddleOcrServingEndpoint + "/predict/ocr_det";
        _paddleOcrRecognitionEndpointsKeyByScript = new()
        {
            {"zh-Hans", paddleOcrServingEndpoint + "/predict/ocr_rec"},
            {"zh-Hant", paddleOcrServingEndpoint + "/predict/ocr_rec_zh-Hant"},
            {"ja", paddleOcrServingEndpoint + "/predict/ocr_rec_ja"},
            {"en", paddleOcrServingEndpoint + "/predict/ocr_rec_en"},
        };

        var tesseractDataPath = _config.GetValue("TesseractDataPath", "") ?? "";
        Tesseract CreateTesseract(string scripts) =>
            new(tesseractDataPath, scripts, OcrEngineMode.LstmOnly)
            { // https://pyimagesearch.com/2021/11/15/tesseract-page-segmentation-modes-psms-explained-how-to-improve-your-ocr-accuracy/
                PageSegMode = PageSegMode.SingleBlockVertText
            };
        _tesseractInstancesKeyByScript = new()
        {
            {"zh-Hans_vert", CreateTesseract("best/chi_sim_vert")},
            {"zh-Hant_vert", CreateTesseract("best/chi_tra_vert")},
            {"ja_vert", CreateTesseract("best/jpn_vert")}
        };
        _tesseractConfidenceThreshold = _config.GetValue("TesseractConfidenceThreshold", 20f);
        _aspectRatioThresholdToUseTesseract = _config.GetValue("AspectRatioThresholdToUseTesseract", 0.8f);
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        var imagesUrlFilename = new List<string> {""};
        var imagesKeyByUrlFilename = (await Task.WhenAll(
                imagesUrlFilename.Select(async filename =>
                    (filename, bytes: await _http.GetByteArrayAsync(filename + ".jpg", stoppingToken)))))
            .ToDictionary(t => t.filename, t => t.bytes);
        var processedImagesTextBoxes = (await RequestPaddleOcrForDetection(imagesKeyByUrlFilename, stoppingToken))
            .Select(ProcessTextBoxes).ToList();
        var reprocessedImagesTextBoxes = (await Task.WhenAll(
                processedImagesTextBoxes.Select(i =>
                    RequestPaddleOcrForDetection(i.ProcessedTextBoxes
                        .Where(b => b.RotationDegrees != 0) // rerun detect and process for cropped images of text boxes with non-zero rotation degrees
                        .ToDictionary(b => b.TextBoxBoundary, b => CvInvoke.Imencode(".png", b.ProcessedTextBoxMat)), stoppingToken))))
            .Select(imageDetectionResults => imageDetectionResults.Select(ProcessTextBoxes));
        var mergedTextBoxesPerImage = processedImagesTextBoxes
            .Zip(reprocessedImagesTextBoxes)
            .Select(t => (t.First.ImageId,
                TextBoxes: t.First.ProcessedTextBoxes
                    .Where(b => b.RotationDegrees == 0)
                    .Concat(t.Second.SelectMany(i => i.ProcessedTextBoxes))));
        _logger.LogInformation("{}", JsonSerializer.Serialize(await Task.WhenAll(mergedTextBoxesPerImage.Select(async t =>
        {
            var boxesUsingTesseractToRecognize = t.TextBoxes.Where(b =>
                (float)b.ProcessedTextBoxMat.Width / b.ProcessedTextBoxMat.Height < _aspectRatioThresholdToUseTesseract).ToList();
            var boxesUsingPaddleOcrToRecognize = t.TextBoxes
                .ExceptBy(boxesUsingTesseractToRecognize.Select(b => b.TextBoxBoundary), b => b.TextBoxBoundary);
            return (t.ImageId, Texts: boxesUsingTesseractToRecognize
                .Select(RecognizeTextViaTesseract)
                .Concat(await RecognizeTextViaPaddleOcr(boxesUsingPaddleOcrToRecognize, stoppingToken)));
        }))));
    }

    private record RecognizedResult(string TextBoxBoundary, string Script, string Text);

    private Task<IEnumerable<RecognizedResult>[]> RecognizeTextViaPaddleOcr
        (IEnumerable<ProcessedTextBox> textBoxes, CancellationToken stoppingToken = default)
    {
        var boxesKeyByBoundary = textBoxes.ToDictionary(b => b.TextBoxBoundary, b =>
        {
            using var mat = b.ProcessedTextBoxMat;
            return CvInvoke.Imencode(".png", mat);
        });
        return Task.WhenAll(_paddleOcrRecognitionEndpointsKeyByScript.Select(async pair =>
            (await RequestPaddleOcrForRecognition(pair.Value, boxesKeyByBoundary, stoppingToken))
            .SelectMany(results => results.Select(result =>
                new RecognizedResult(result.ImageId, pair.Key, result.Text)))));
    }

    private IEnumerable<RecognizedResult> RecognizeTextViaTesseract(ProcessedTextBox textBox)
    {
        using var mat = textBox.ProcessedTextBoxMat;
        CvInvoke.CvtColor(mat, mat, ColorConversion.Bgr2Gray);
        // https://docs.opencv.org/4.7.0/d7/d4d/tutorial_py_thresholding.html
        // http://www.fmwconcepts.com/imagemagick/threshold_comparison/index.php
        _ = CvInvoke.Threshold(mat, mat, 0, 255, ThresholdType.Binary | ThresholdType.Otsu);
        // CvInvoke.AdaptiveThreshold(threshedMat, threshedMat, 255, AdaptiveThresholdType.GaussianC, ThresholdType.Binary, 11, 3);
        // CvInvoke.MedianBlur(threshedMat, threshedMat, 1); // https://en.wikipedia.org/wiki/Salt-and-pepper_noise

        using var histogram = new Mat();
        using var threshedMatVector = new VectorOfMat(mat);
        CvInvoke.CalcHist(threshedMatVector, new[] {0}, null, histogram, new[] {256}, new[] {0f, 256}, false);
        // we don't need k-means clustering like https://stackoverflow.com/questions/50899692/most-dominant-color-in-rgb-image-opencv-numpy-python
        // since mat only composed of pure black and whites(aka 1bpp) after thresholding
        var dominantColor = histogram.Get<float>(0, 0) > histogram.Get<float>(255, 0) ? 0 : 255;
        // https://github.com/tesseract-ocr/tesseract/issues/427
        CvInvoke.CopyMakeBorder(mat, mat, 10, 10, 10, 10, BorderType.Constant, new(dominantColor, dominantColor, dominantColor));

        return _tesseractInstancesKeyByScript.Select(pair =>
        {
            var (script, tesseract) = pair;
            tesseract.SetImage(mat);
            if (tesseract.Recognize() != 0) return new(textBox.TextBoxBoundary, script, "");
            var text = tesseract.GetCharacters()
                .Where(c => c.Cost > _tesseractConfidenceThreshold)
                .Aggregate("", (acc, c) => acc + c.Text.Normalize(NormalizationForm.FormKC)); // https://unicode.org/reports/tr15/
            return new RecognizedResult(textBox.TextBoxBoundary, script, text);
        }).ToList(); // eager eval since mat is already disposed after return
    }

    private record ProcessedTextBox(string TextBoxBoundary, Mat ProcessedTextBoxMat, float RotationDegrees);

    private record ImageAndProcessedTextBoxes(string ImageId, List<ProcessedTextBox> ProcessedTextBoxes);

    private static ImageAndProcessedTextBoxes ProcessTextBoxes(PaddleOcrDetectionResult detectionResult)
    {
        using var imageMat = new Mat();
        CvInvoke.Imdecode(detectionResult.ImageBytes, ImreadModes.Unchanged, imageMat);
        return new(detectionResult.ImageId, detectionResult.TextBoxes
            .Select(textBoxAndDegrees =>
            {
                var (textBox, degrees) = textBoxAndDegrees;
                var circumscribed = textBox.ToCircumscribedRectangle();
                var processedMat = new Mat(imageMat, circumscribed); // crop by circumscribed rectangle
                if (degrees != 0) processedMat.Rotate(degrees);
                var rectangleBoundary = $"{circumscribed.Width}x{circumscribed.Height}@{circumscribed.X},{circumscribed.Y}";
                return new ProcessedTextBox(rectangleBoundary, processedMat, degrees);
            })
            .ToList()); // eager eval since imageMat is already disposed after return
    }

    private record TextBoxAndDegrees(PaddleOcrResponse.TextBox TextBox, float RotationDegrees);

    private record PaddleOcrDetectionResult(string ImageId, byte[] ImageBytes, IEnumerable<TextBoxAndDegrees> TextBoxes);

    private Task<IEnumerable<PaddleOcrDetectionResult>> RequestPaddleOcrForDetection
        (Dictionary<string, byte[]> imagesKeyById, CancellationToken stoppingToken = default) =>
        RequestPaddleOcr(_paddleOcrDetectionEndpoint, imagesKeyById, t =>
            new PaddleOcrDetectionResult(t.ImageId, t.ImageBytes, t.Results
                .Select(i => new TextBoxAndDegrees(i.TextBox!, i.TextBox!.GetRotationDegrees()))), stoppingToken);

    private record PaddleOcrRecognitionResult(string ImageId, string Text, float Confidence);

    private Task<IEnumerable<IEnumerable<PaddleOcrRecognitionResult>>> RequestPaddleOcrForRecognition
        (string paddleOcrEndpoint, Dictionary<string, byte[]> imagesKeyById, CancellationToken stoppingToken = default) =>
        RequestPaddleOcr(paddleOcrEndpoint, imagesKeyById,
            t => t.Results.Select(result =>
                new PaddleOcrRecognitionResult(t.ImageId, result.Text!, result.Confidence!.Value)),
            stoppingToken);

    private static async Task<IEnumerable<T>>
        RequestPaddleOcr<T>(string paddleOcrEndpoint, Dictionary<string, byte[]> imagesKeyById,
            Func<(string ImageId, byte[] ImageBytes, PaddleOcrResponse.Result[] Results), T> paddleOcrResponseTransformer,
            CancellationToken stoppingToken = default) where T : class
    {
        if (!imagesKeyById.Values.Any()) return Array.Empty<T>();
        var requestPayload = new PaddleOcrRequestPayload(imagesKeyById.Values.Select(Convert.ToBase64String));
        var httpResponse = await _http.PostAsJsonAsync(paddleOcrEndpoint, requestPayload, stoppingToken);
        var response = await httpResponse.Content.ReadFromJsonAsync<PaddleOcrResponse>
            (PaddleOcrResponse.JsonSerializerOptions, stoppingToken);
        if (!httpResponse.IsSuccessStatusCode
            || response?.Results == null
            || response.Msg != ""
            || response.Status != "000")
            throw new($"{paddleOcrEndpoint} responded with non zero status or empty msg"
                      + $"raw={await httpResponse.Content.ReadAsStringAsync(stoppingToken)}");
        return imagesKeyById.Zip(response.Results, (pair, results) => (pair.Key, pair.Value, results)).Select(paddleOcrResponseTransformer);
    }

    private record PaddleOcrRequestPayload(IEnumerable<string> Images);

    private record PaddleOcrResponse(string Msg, string Status, PaddleOcrResponse.Result[][]? Results)
    {
        private class ResultsConverter : JsonConverter<Result[][]>
        {
            public override Result[][]? Read(ref Utf8JsonReader reader, Type typeToConvert, JsonSerializerOptions options) =>
                reader.TokenType == JsonTokenType.StartArray ? JsonSerializer.Deserialize<Result[][]>(ref reader, options) : null;

            public override void Write(Utf8JsonWriter writer, Result[][] value, JsonSerializerOptions options) => throw new NotImplementedException();
        }

        [JsonConverter(typeof(ResultsConverter))]
        public Result[][]? Results { get; } = Results;

        public static readonly JsonSerializerOptions JsonSerializerOptions = new() { PropertyNamingPolicy = JsonNamingPolicy.CamelCase};

        public record Result(TextBox? TextBox, string? Text, float? Confidence)
        {
            [JsonPropertyName("text_region")]
            public TextBox? TextBox { get; } = TextBox;
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
    public static void Rotate(this Mat src, float degrees)
    {
        degrees = -degrees; // counter-clockwise to clockwise
        var center = new PointF((src.Width - 1) / 2f, (src.Height - 1) / 2f);
        using var rotationMat = new Mat();
        CvInvoke.GetRotationMatrix2D(center, degrees, 1, rotationMat);
        var boundingRect = new RotatedRect(new(), src.Size, degrees).MinAreaRect();
        rotationMat.Set(0, 2, rotationMat.Get<double>(0, 2) + (boundingRect.Width / 2f) - (src.Width / 2f));
        rotationMat.Set(1, 2, rotationMat.Get<double>(1, 2) + (boundingRect.Height / 2f) - (src.Height / 2f));
        CvInvoke.WarpAffine(src, src, rotationMat, boundingRect.Size);
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
