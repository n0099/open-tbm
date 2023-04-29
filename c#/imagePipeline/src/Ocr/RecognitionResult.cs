using OpenCvSharp;

namespace tbm.ImagePipeline.Ocr;

public interface IRecognitionResult
{
    public uint ImageId { get; }
    public string Script { get; }
    public RotatedRect TextBox { get; }
    public string Text { get; }
    public ushort Confidence { get; }
}

public record PaddleOcrRecognitionResult(uint ImageId, string Script,
    RotatedRect TextBox, string Text, ushort Confidence) : IRecognitionResult;

public record TesseractRecognitionResult(uint ImageId, string Script,
    RotatedRect TextBox, string Text, ushort Confidence,
    bool IsVertical, bool IsUnrecognized, bool ShouldFallbackToPaddleOcr) : IRecognitionResult;
