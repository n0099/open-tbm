// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.ImagePipeline.Db;

public class ImageHash : ImageWithFrameIndex
{
    public required byte[] PHash { get; set; }
    public required byte[] AverageHash { get; set; }
    public required byte[] BlockMeanHash { get; set; }
    public required byte[] MarrHildrethHash { get; set; }
    public required byte[] ThumbHash { get; set; }
}
