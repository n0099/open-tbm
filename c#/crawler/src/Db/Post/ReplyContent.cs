// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class ReplyContent
{
    [Key] public ulong Pid { get; set; }
    public byte[]? Content { get; set; }
}
