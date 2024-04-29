// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.ImagePipeline.Db;

public class ImageOcrLine
{
    public uint ImageId { get; set; }
    public uint FrameIndex { get; set; }
    public required string TextLines { get; set; }
}
