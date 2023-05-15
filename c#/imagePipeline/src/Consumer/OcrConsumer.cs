namespace tbm.ImagePipeline.Consumer;

public class OcrConsumer : MatrixConsumer
{
    private readonly JoinedRecognizer _recognizer;

    public delegate OcrConsumer New(string script);

    public OcrConsumer(JoinedRecognizer.New recognizerFactory, string script) =>
        _recognizer = recognizerFactory(script);

    public Task InitializePaddleOcr(CancellationToken stoppingToken = default) =>
        _recognizer.InitializePaddleOcr(stoppingToken);

    protected override void ConsumeInternal
        (ImagePipelineDbContext db, Dictionary<ImageId, Mat> matricesKeyByImageId, CancellationToken stoppingToken)
    {
        var recognizedResults = _recognizer.RecognizeImageMatrices(matricesKeyByImageId).ToList();
        var recognizedTextLinesKeyByImageId = _recognizer.GetRecognizedTextLinesKeyByImageId(recognizedResults);
        db.ImageOcrBoxes.AddRange(recognizedResults.Select(result => new ImageOcrBox
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
        db.ImageOcrLines.AddRange(recognizedTextLinesKeyByImageId.Select(pair => new ImageOcrLine
        {
            ImageId = pair.Key,
            TextLines = pair.Value
        }));
    }
}
