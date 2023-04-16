using OpenCvSharp;
using tbm.Crawler.ImagePipeline.Ocr;

namespace tbm.Crawler.Worker;

public class ImageOcrPipelineWorker : ErrorableWorker
{
    private readonly ILogger<ImageOcrPipelineWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private static HttpClient _http = null!;

    public ImageOcrPipelineWorker(ILogger<ImageOcrPipelineWorker> logger, ILifetimeScope scope0,
        IHttpClientFactory httpFactory) : base(logger)
    {
        _logger = logger;
        _scope0 = scope0;
        _http = httpFactory.CreateClient("tbImage");
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await using var scope1 = _scope0.BeginLifetimeScope();
        var db = scope1.Resolve<TbmDbContext.New>()(0);
        uint lastImageIdInPreviousBatch = 0;
        var isNoMoreImages = false;
        while (!isNoMoreImages)
        {
            var images = (from image in db.Images.AsNoTracking()
                where image.ImageId > lastImageIdInPreviousBatch
                    && !db.ImageOcrBoxes.AsNoTracking().Select(e => e.ImageId).Contains(image.ImageId)
                    && !db.ImageOcrLines.AsNoTracking().Select(e => e.ImageId).Contains(image.ImageId)
                orderby image.ImageId
                select image).Take(1000).ToList();
            if (!images.Any()) isNoMoreImages = true;
            else
            {
                foreach (var chunkedImages in images.Chunk(16))
                {
                    await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);
                    await RecognizeTextsThenSave(scope1, db, chunkedImages, "zh-Hans", stoppingToken);
                    await RecognizeTextsThenSave(scope1, db, chunkedImages, "zh-Hant", stoppingToken);
                    await RecognizeTextsThenSave(scope1, db, chunkedImages, "ja", stoppingToken);
                    await RecognizeTextsThenSave(scope1, db, chunkedImages, "en", stoppingToken);
                    _ = await db.SaveChangesAsync(stoppingToken);
                    await transaction.CommitAsync(stoppingToken);
                }
            }
            lastImageIdInPreviousBatch = images.Last().ImageId;
        }
    }

    private async Task RecognizeTextsThenSave(ILifetimeScope scope, TbmDbContext db,
        IEnumerable<TiebaImage> images, string script, CancellationToken stoppingToken)
    {
        await using var scope1 = scope.BeginLifetimeScope();
        var imagesKeyByUrlFilename = (await Task.WhenAll(images.Select(async image =>
                (image.ImageId, bytes: await _http.GetByteArrayAsync(image.UrlFilename + ".jpg", stoppingToken)))))
            .ToDictionary(t => t.ImageId, t => Cv2.ImDecode(t.bytes, ImreadModes.Color)); // convert to BGR three channels without alpha
        var consumer = scope1.Resolve<ImageOcrConsumer.New>()(script);
        await consumer.InitializePaddleOcrModel(stoppingToken);
        var recognizedResults = consumer.GetRecognizedResults(imagesKeyByUrlFilename);
        _logger.LogInformation("{}", recognizedResults);
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
        var recognizedTextLinesKeyByImageId = consumer.GetRecognizedTextLinesKeyByImageId(recognizedResults);
        _logger.LogInformation("{}", recognizedTextLinesKeyByImageId);
        db.ImageOcrLines.AddRange(recognizedTextLinesKeyByImageId.Select(pair => new TiebaImageOcrLines
        {
            ImageId = pair.Key,
            Script = script,
            TextLines = pair.Value
        }));
    }
}
