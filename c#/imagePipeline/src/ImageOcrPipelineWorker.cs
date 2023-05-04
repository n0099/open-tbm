using System.Data;

namespace tbm.ImagePipeline;

public class ImageOcrPipelineWorker : ErrorableWorker
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private static HttpClient _http = null!;
    private readonly int _batchSize;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, IConfiguration config,
        ILifetimeScope scope0, IHttpClientFactory httpFactory) : base(logger)
    {
        _logger = logger;
        _scope0 = scope0;
        _http = httpFactory.CreateClient("tbImage");
        var configSection = config.GetSection("ImageOcrPipeline");
        _batchSize = configSection.GetValue("BatchSize", 1000);
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        foreach (var script in new[] {"zh-Hans", "zh-Hant", "ja", "en"})
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()(script);
            var consumer = scope1.Resolve<ImageOcrConsumer.New>()(script);
            await consumer.InitializePaddleOcr(stoppingToken);
            ImageId lastImageIdInPreviousBatch = 0;
            var isNoMoreImages = false;
            while (!isNoMoreImages)
            {
                var images = (from image in db.Images.AsNoTracking()
                    where image.ImageId > lastImageIdInPreviousBatch
                          && !db.ImageOcrBoxes.AsNoTracking().Select(e => e.ImageId).Contains(image.ImageId)
                          && !db.ImageOcrLines.AsNoTracking().Select(e => e.ImageId).Contains(image.ImageId)
                    orderby image.ImageId
                    select image).Take(_batchSize).ToList();
                if (!images.Any()) isNoMoreImages = true;
                else await RecognizeScriptsInImages(db, consumer, images, stoppingToken);
                lastImageIdInPreviousBatch = images.Last().ImageId;
            }
        }
    }

    private static async Task RecognizeScriptsInImages(ImagePipelineDbContext db, ImageOcrConsumer consumer,
        IEnumerable<TiebaImage> images, CancellationToken stoppingToken)
    {
        var matricesKeyByImageId = (await Task.WhenAll(images.Select(async image =>
                (image.ImageId, bytes: await _http.GetByteArrayAsync(image.UrlFilename + ".jpg", stoppingToken)))))
            .ToDictionary(t => t.ImageId, t =>
                Cv2.ImDecode(t.bytes, ImreadModes.Color)); // convert to BGR three channels without alpha
        await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);
        RecognizeTextsThenSave(db, consumer, matricesKeyByImageId);
        matricesKeyByImageId.Values.ForEach(mat => mat.Dispose());
        _ = await db.SaveChangesAsync(stoppingToken);
        await transaction.CommitAsync(stoppingToken);
    }

    private static void RecognizeTextsThenSave(ImagePipelineDbContext db, ImageOcrConsumer consumer,
        Dictionary<ImageId, Mat> matricesKeyByImageId)
    {
        var recognizedResults = consumer.RecognizeImageMatrices(matricesKeyByImageId).ToList();
        var recognizedTextLinesKeyByImageId = consumer.GetRecognizedTextLinesKeyByImageId(recognizedResults);
        SaveRecognizedTexts(db, recognizedResults, recognizedTextLinesKeyByImageId);
    }

    private static void SaveRecognizedTexts(ImagePipelineDbContext db,
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
                TesseractRecognitionResult {IsVertical: false} => "TesseractHorizontal",
                TesseractRecognitionResult {IsVertical: true} => "TesseractVertical",
                _ => throw new ArgumentOutOfRangeException(nameof(result), result, null)
            },
            Confidence = result.Confidence,
            Text = result.Text
        }));
        db.ImageOcrLines.AddRange(recognizedTextLinesKeyByImageId.Select(pair => new TiebaImageOcrLines
        {
            ImageId = pair.Key,
            TextLines = pair.Value
        }));
    }
}
