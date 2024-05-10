// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.ImagePipeline.Db;

public class ImageHash : RowVersionedEntity
{
    public uint ImageId { get; set; }
    public uint FrameIndex { get; set; }
    public required byte[] PHash { get; set; }
    public required byte[] AverageHash { get; set; }
    public required byte[] BlockMeanHash { get; set; }
    public required byte[] MarrHildrethHash { get; set; }
    public required byte[] ThumbHash { get; set; }
}
