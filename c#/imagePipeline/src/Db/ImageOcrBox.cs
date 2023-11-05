namespace tbm.ImagePipeline.Db;

public class ImageOcrBox
{
    public uint ImageId { get; set; }
    public uint FrameIndex { get; set; }
    public short CenterPointX { get; set; }
    public short CenterPointY { get; set; }
    public short Width { get; set; }
    public short Height { get; set; }
    public short RotationDegrees { get; set; }
    public string Recognizer { get; set; } = "";
    public ushort Confidence { get; set; }
    public string Text { get; set; } = "";
}
