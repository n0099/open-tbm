// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.ImagePipeline.Db;

public class ImageOcrLine : ImageWithFrameIndex
{
    public uint Fid { get; set; }
    public required string Script { get; set; }
    public required string TextLines { get; set; }
}
