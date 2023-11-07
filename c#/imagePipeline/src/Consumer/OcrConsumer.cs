namespace tbm.ImagePipeline.Consumer;

public class OcrConsumer(JointRecognizer.New recognizerFactory, ExceptionHandler exceptionHandler, string script)
    : MatrixConsumer
{
    public delegate OcrConsumer New(string script);

    private readonly JointRecognizer _recognizer = recognizerFactory(script);

    public async Task InitializePaddleOcr(CancellationToken stoppingToken = default) =>
        await _recognizer.InitializePaddleOcr(stoppingToken);

    protected override void ConsumeInternal(
        ImagePipelineDbContext db,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default)
    {
        var matricesKeyByImageKey = imageKeysWithMatrix.Select(
            exceptionHandler.TryWithData<ImageKeyWithMatrix, KeyValuePair<ImageKey, Mat>>(
                i => i.ImageId,
                i =>
                {
                    var mat = i.Matrix;
                    if (mat.Channels() == 4) // https://github.com/sdcb/PaddleSharp/blob/2.4.1.2/src/Sdcb.PaddleOCR/PaddleOcrDetector.cs#L62
                        Cv2.CvtColor(mat, mat, ColorConversionCodes.BGRA2BGR);
                    return KeyValuePair.Create(new ImageKey(i.ImageId, i.FrameIndex), mat);
                })
            ).Somes().ToDictionary();
        var recognizedResults = _recognizer.RecognizeMatrices(matricesKeyByImageKey, stoppingToken).ToList();
        db.ImageOcrBoxes.AddRange(recognizedResults.Select(result => new ImageOcrBox
        {
            ImageId = result.ImageKey.ImageId,
            FrameIndex = result.ImageKey.FrameIndex,
            CenterPointX = result.TextBox.Center.X.RoundToUshort(),
            CenterPointY = result.TextBox.Center.Y.RoundToUshort(),
            Width = result.TextBox.Size.Width.RoundToUshort(),
            Height = result.TextBox.Size.Height.RoundToUshort(),
            RotationDegrees = result.TextBox.Angle.RoundToUshort(),
            Recognizer = result switch
            {
                PaddleOcrRecognitionResult r => "PaddleOCR" + Enum.GetName(r.ModelVersion)?.ToLower(),
                TesseractRecognitionResult {IsVertical: false} => "TesseractHorizontal",
                TesseractRecognitionResult {IsVertical: true} => "TesseractVertical",
                _ => throw new ArgumentOutOfRangeException(nameof(result), result, null)
            },
            Confidence = result.Confidence,
            Text = result.Text
        }));
        var recognizedTextLinesKeyByImageKey = _recognizer.GetRecognizedTextLines(recognizedResults);
        db.ImageOcrLines.AddRange(recognizedTextLinesKeyByImageKey.Select(pair => new ImageOcrLine
        {
            ImageId = pair.Key.ImageId,
            FrameIndex = pair.Key.FrameIndex,
            TextLines = pair.Value
        }));
    }
}
