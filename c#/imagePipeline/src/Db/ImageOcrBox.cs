namespace tbm.ImagePipeline.Db;

public class ImageOcrBox
{
    public uint ImageId { get; set; }
    public uint FrameIndex { get; set; }
    public float CenterPointX { get; set; }
    public float CenterPointY { get; set; }
    public float Width { get; set; }
    public float Height { get; set; }
    public float RotationDegrees { get; set; }
    public string Recognizer { get; set; } = "";
    public ushort Confidence { get; set; }
    public string Text { get; set; } = "";
}
