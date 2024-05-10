// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.ImagePipeline.Db;

public class ImageOcrLine : ImageWithFrameIndex
{
    public required string TextLines { get; set; }
}
