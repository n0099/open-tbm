namespace tbm.Crawler.Db.Post;

public class ReplyContent
{
    [Key] public ulong Pid { get; set; }
    public byte[]? ProtoBufBytes { get; set; }
}
