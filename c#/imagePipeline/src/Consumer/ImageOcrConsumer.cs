namespace tbm.ImagePipeline.Consumer;

public class ImageOcrConsumer : ImageBaseConsumer
{
    private readonly JoinedRecognizer _recognizer;

    public delegate ImageOcrConsumer New(string script);

    public ImageOcrConsumer(ImagePipelineDbContext.New dbContextFactory,
        JoinedRecognizer.New recognizerFactory,
        string script
    ) : base(dbContextFactory, script) =>
        _recognizer = recognizerFactory(script);

    public Task InitializePaddleOcr(CancellationToken stoppingToken = default) =>
        _recognizer.InitializePaddleOcr(stoppingToken);

    protected override Task ConsumeInternal
        (ImagePipelineDbContext db, Dictionary<ImageId, Mat> matricesKeyByImageId, CancellationToken stoppingToken)
    {
        var recognizedResults = _recognizer.RecognizeImageMatrices(matricesKeyByImageId).ToList();
        var recognizedTextLinesKeyByImageId = _recognizer.GetRecognizedTextLinesKeyByImageId(recognizedResults);
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
                TesseractRecognitionResult { IsVertical: false } => "TesseractHorizontal",
                TesseractRecognitionResult { IsVertical: true } => "TesseractVertical",
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
        return Task.CompletedTask;
    }
}
