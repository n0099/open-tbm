namespace tbm.ImagePipeline.Db;

public class ImageQrCode
{
    public uint ImageId { get; set; }
    public uint FrameIndex { get; set; }
    public float Point1X { get; set; }
    public float Point1Y { get; set; }
    public float Point2X { get; set; }
    public float Point2Y { get; set; }
    public float Point3X { get; set; }
    public float Point3Y { get; set; }
    public float Point4X { get; set; }
    public float Point4Y { get; set; }
    public required string Text { get; set; }
}
