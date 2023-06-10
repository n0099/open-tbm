namespace tbm.ImagePipeline.Ocr;

public interface IRecognitionResult
{
    public ImageKey ImageKey { get; }
    public RotatedRect TextBox { get; }
    public string Text { get; }
    public ushort Confidence { get; }
}

public record PaddleOcrRecognitionResult
    (ImageKey ImageKey, RotatedRect TextBox, string Text, ushort Confidence)
    : IRecognitionResult;

public record TesseractRecognitionResult
    (ImageKey ImageKey, RotatedRect TextBox, string Text, ushort Confidence,
        bool IsVertical, bool ShouldFallbackToPaddleOcr)
    : IRecognitionResult;
