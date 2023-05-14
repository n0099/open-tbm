namespace tbm.ImagePipeline.Db;

public class TiebaImageOcrLine
{
    [Key] public uint ImageId { get; set; }
    public string TextLines { get; set; } = "";
}
