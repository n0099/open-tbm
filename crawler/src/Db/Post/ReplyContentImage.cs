namespace tbm.Crawler.Db.Post;

public class ReplyContentImage
{
    [Key] public ulong Pid { get; set; }
    public string UrlFilename { get; set; } = "";
}
