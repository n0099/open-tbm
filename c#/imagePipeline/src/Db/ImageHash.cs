// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.ImagePipeline.Db;

public class ImageHash
{
    public uint ImageId { get; set; }
    public uint FrameIndex { get; set; }
    public ulong PHash { get; set; }
    public ulong AverageHash { get; set; }
    public required byte[] BlockMeanHash { get; set; }
    public required byte[] MarrHildrethHash { get; set; }
    public required byte[] ThumbHash { get; set; }
}
