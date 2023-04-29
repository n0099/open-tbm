namespace tbm.ImagePipeline.Ocr;

public interface IRecognitionResult
{
    public ImageId ImageId { get; }
    public string Script { get; }
    public RotatedRect TextBox { get; }
    public string Text { get; }
    public ushort Confidence { get; }
}

public record PaddleOcrRecognitionResult(ImageId ImageId, string Script,
    RotatedRect TextBox, string Text, ushort Confidence) : IRecognitionResult;

public record TesseractRecognitionResult(ImageId ImageId, string Script,
    RotatedRect TextBox, string Text, ushort Confidence,
    bool IsVertical, bool IsUnrecognized, bool ShouldFallbackToPaddleOcr) : IRecognitionResult;
