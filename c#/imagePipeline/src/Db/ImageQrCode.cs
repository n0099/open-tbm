// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.ImagePipeline.Db;

public class ImageQrCode : RowVersionedEntity
{
    public uint ImageId { get; set; }
    public uint FrameIndex { get; set; }
    public short Point1X { get; set; }
    public short Point1Y { get; set; }
    public short Point2X { get; set; }
    public short Point2Y { get; set; }
    public short Point3X { get; set; }
    public short Point3Y { get; set; }
    public short Point4X { get; set; }
    public short Point4Y { get; set; }
    public required string Text { get; set; }
}
