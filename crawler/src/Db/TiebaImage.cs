namespace tbm.Crawler.Db;

public class TiebaImage
{
    [Key] public uint ImageId { get; set; }
    public string UrlFilename { get; set; } = "";
    public uint ByteSize { get; set; }
}
