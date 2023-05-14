namespace tbm.ImagePipeline.Db;

public class ImageOcrLine
{
    [Key] public uint ImageId { get; set; }
    public string TextLines { get; set; } = "";
}
