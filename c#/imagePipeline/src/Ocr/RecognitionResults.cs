namespace tbm.ImagePipeline.Ocr;

public interface IRecognitionResult
{
    public ImageKey ImageKey { get; }
    public string Script { get; }
    public RotatedRect TextBox { get; }
    public string Text { get; }
    public ushort Confidence { get; }
}

public record PaddleOcrRecognitionResult
    (ImageKey ImageKey, string Script, RotatedRect TextBox, string Text, ushort Confidence)
    : IRecognitionResult;

public record TesseractRecognitionResult
    (ImageKey ImageKey, string Script, RotatedRect TextBox, string Text, ushort Confidence,
        bool IsVertical, bool IsUnrecognized, bool ShouldFallbackToPaddleOcr)
    : IRecognitionResult;
