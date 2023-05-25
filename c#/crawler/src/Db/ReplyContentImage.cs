namespace tbm.Crawler.Db;

public class ReplyContentImage
{
    public ulong Pid { get; set; }
    public uint ImageId { get; set; }
    public required ImageInReply ImageInReply { get; set; }
}
