namespace tbm.ImagePipeline.Db;

public class TiebaImageOcrLines
{
    [Key] public uint ImageId { get; set; }
    public string Script { get; set; } = "";
    public string TextLines { get; set; } = "";
}
