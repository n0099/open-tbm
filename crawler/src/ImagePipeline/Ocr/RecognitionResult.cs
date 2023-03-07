using OpenCvSharp;

namespace tbm.Crawler.ImagePipeline.Ocr;

public interface IRecognitionResult
{
    public string ImageId { get; }
    public string Script { get; }
    public RotatedRect TextBox { get; }
    public string Text { get; }
    public ushort Confidence { get; }
}

public record PaddleOcrRecognitionResult(string ImageId, string Script,
    RotatedRect TextBox, string Text, ushort Confidence) : IRecognitionResult;

public record TesseractRecognitionResult(string ImageId, string Script, bool IsVertical, bool IsUnrecognized,
    RotatedRect TextBox, string Text, ushort Confidence) : IRecognitionResult;
