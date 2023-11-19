namespace tbm.ImagePipeline.Db;

public class ImageFailed
{
    [Key] public uint ImageId { get; set; }
    public required string Exception { get; set; }
}
