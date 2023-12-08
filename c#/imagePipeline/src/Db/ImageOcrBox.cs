// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.ImagePipeline.Db;

public class ImageOcrBox
{
    public uint ImageId { get; set; }
    public uint FrameIndex { get; set; }
    public ushort CenterPointX { get; set; }
    public ushort CenterPointY { get; set; }
    public ushort Width { get; set; }
    public ushort Height { get; set; }
    public ushort RotationDegrees { get; set; }
    public string Recognizer { get; set; } = "";
    public byte Confidence { get; set; }
    public string Text { get; set; } = "";
}
