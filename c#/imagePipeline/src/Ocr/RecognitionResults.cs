using Sdcb.PaddleOCR.Models;

namespace tbm.ImagePipeline.Ocr;

public interface IRecognitionResult
{
    public ImageKey ImageKey { get; }
    public RotatedRect TextBox { get; }
    public string Text { get; }
    public byte Confidence { get; }
}

public record PaddleOcrRecognitionResult
    (ImageKey ImageKey, RotatedRect TextBox, string Text, byte Confidence,
        ModelVersion ModelVersion)
    : IRecognitionResult;

public record TesseractRecognitionResult
    (ImageKey ImageKey, RotatedRect TextBox, string Text, byte Confidence,
        bool IsVertical, bool ShouldFallbackToPaddleOcr)
    : IRecognitionResult;
