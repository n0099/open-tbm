using System.Data;

namespace tbm.ImagePipeline;

public class ImageOcrPipelineWorker : ErrorableWorker
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private static HttpClient _http = null!;
    private readonly int _batchReadFromDbSize;
    private readonly int _batchRecognizeSize;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IConfiguration config,
        ILifetimeScope scope0, IHttpClientFactory httpFactory) : base(logger)
    {
        _logger = logger;
        _scope0 = scope0;
        _http = httpFactory.CreateClient("tbImage");
        var configSection = config.GetSection("ImageOcrPipeline");
        _batchReadFromDbSize = configSection.GetValue("BatchReadFromDbSize", 1000);
        _batchRecognizeSize = configSection.GetValue("BatchRecognizeSize", 16);
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await using var scope1 = _scope0.BeginLifetimeScope();
        var db = scope1.Resolve<ImagePipelineDbContext.New>()("");
        ImageId lastImageIdInPreviousBatch = 0;
        var isNoMoreImages = false;
        while (!isNoMoreImages)
        {
            var images = (from image in db.Images.AsNoTracking()
                where image.ImageId > lastImageIdInPreviousBatch
                    && !db.ImageOcrBoxes.AsNoTracking().Select(e => e.ImageId).Contains(image.ImageId)
                    && !db.ImageOcrLines.AsNoTracking().Select(e => e.ImageId).Contains(image.ImageId)
                orderby image.ImageId
                select image).Take(_batchReadFromDbSize).ToList();
            if (!images.Any()) isNoMoreImages = true;
            else foreach (var chunkedImages in images.Chunk(_batchRecognizeSize))
                await RecognizeScriptsInImages(scope1, chunkedImages, stoppingToken);
            lastImageIdInPreviousBatch = images.Last().ImageId;
        }
    }

    private static async Task RecognizeScriptsInImages(ILifetimeScope scope, IEnumerable<TiebaImage> images, CancellationToken stoppingToken)
    {
        await using var scope1 = scope.BeginLifetimeScope();
        var db = scope1.Resolve<ImagePipelineDbContext.New>()("");
        var matricesKeyByImageId = (await Task.WhenAll(images.Select(async image =>
                (image.ImageId, bytes: await _http.GetByteArrayAsync(image.UrlFilename + ".jpg", stoppingToken)))))
            .ToDictionary(t => t.ImageId, t =>
                Cv2.ImDecode(t.bytes, ImreadModes.Color)); // convert to BGR three channels without alpha
        await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);
        await RecognizeTextsThenSave(scope, db, matricesKeyByImageId, "zh-Hans", stoppingToken);
        await RecognizeTextsThenSave(scope, db, matricesKeyByImageId, "zh-Hant", stoppingToken);
        await RecognizeTextsThenSave(scope, db, matricesKeyByImageId, "ja", stoppingToken);
        await RecognizeTextsThenSave(scope, db, matricesKeyByImageId, "en", stoppingToken);
        matricesKeyByImageId.Values.ForEach(mat => mat.Dispose());
        _ = await db.SaveChangesAsync(stoppingToken);
        await transaction.CommitAsync(stoppingToken);
    }

    private static async Task RecognizeTextsThenSave(ILifetimeScope scope, ImagePipelineDbContext db,
        Dictionary<ImageId, Mat> matricesKeyByImageId, string script, CancellationToken stoppingToken)
    {
        await using var scope1 = scope.BeginLifetimeScope();
        var consumer = scope1.Resolve<ImageOcrConsumer.New>()(script);
        await consumer.InitializePaddleOcr(stoppingToken);
        var recognizedResults = consumer.RecognizeImageMatrices(matricesKeyByImageId).ToList();
        var recognizedTextLinesKeyByImageId = consumer.GetRecognizedTextLinesKeyByImageId(recognizedResults);
        SaveRecognizedTexts(db, script, recognizedResults, recognizedTextLinesKeyByImageId);
    }

    private static void SaveRecognizedTexts(ImagePipelineDbContext db, string script,
        IEnumerable<IRecognitionResult> recognizedResults, Dictionary<ImageId, string> recognizedTextLinesKeyByImageId)
    {
        db.ImageOcrBoxes.AddRange(recognizedResults.Select(result => new TiebaImageOcrBoxes
        {
            ImageId = result.ImageId,
            CenterPointX = result.TextBox.Center.X,
            CenterPointY = result.TextBox.Center.Y,
            Width = result.TextBox.Size.Width,
            Height = result.TextBox.Size.Height,
            RotationDegrees = result.TextBox.Angle,
            Recognizer = result switch
            {
                PaddleOcrRecognitionResult => "PaddleOCR",
                TesseractRecognitionResult => "Tesseract",
                _ => ""
            },
            Script = script + (result is TesseractRecognitionResult {IsVertical: true} ? "_vert" : ""),
            Confidence = result.Confidence,
            Text = result.Text
        }));
        db.ImageOcrLines.AddRange(recognizedTextLinesKeyByImageId.Select(pair => new TiebaImageOcrLines
        {
            ImageId = pair.Key,
            Script = script,
            TextLines = pair.Value
        }));
    }
}
