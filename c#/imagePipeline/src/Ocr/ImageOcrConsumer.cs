using System.Data;

namespace tbm.ImagePipeline.Ocr;

public class ImageOcrConsumer
{
    private readonly ImagePipelineDbContext _db;
    private readonly JoinedRecognizer _consumer;

    public delegate ImageOcrConsumer New(string script);

    public ImageOcrConsumer(ImagePipelineDbContext.New dbContextFactory, JoinedRecognizer.New consumerFactory, string script)
    {
        _db = dbContextFactory(script);
        _consumer = consumerFactory(script);
    }

    public Task InitializePaddleOcr(CancellationToken stoppingToken = default) =>
        _consumer.InitializePaddleOcr(stoppingToken);

    public async Task RecognizeScriptsInImages(Dictionary<ImageId, Mat> matricesKeyByImageId, CancellationToken stoppingToken)
    {
        await using var transaction = await _db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);
        RecognizeTextsThenSave(matricesKeyByImageId);
        matricesKeyByImageId.Values.ForEach(mat => mat.Dispose());
        _ = await _db.SaveChangesAsync(stoppingToken);
        await transaction.CommitAsync(stoppingToken);
    }

    private void RecognizeTextsThenSave(Dictionary<ImageId, Mat> matricesKeyByImageId)
    {
        var recognizedResults = _consumer.RecognizeImageMatrices(matricesKeyByImageId).ToList();
        var recognizedTextLinesKeyByImageId = _consumer.GetRecognizedTextLinesKeyByImageId(recognizedResults);
        SaveRecognizedTexts(recognizedResults, recognizedTextLinesKeyByImageId);
    }

    private void SaveRecognizedTexts(IEnumerable<IRecognitionResult> recognizedResults,
        Dictionary<ImageId, string> recognizedTextLinesKeyByImageId)
    {
        _db.ImageOcrBoxes.AddRange(recognizedResults.Select(result => new TiebaImageOcrBoxes
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
        _db.ImageOcrLines.AddRange(recognizedTextLinesKeyByImageId.Select(pair => new TiebaImageOcrLines
        {
            ImageId = pair.Key,
            TextLines = pair.Value
        }));
    }
}
