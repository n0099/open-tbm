// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class ReplyContent : PostContent
{
    [Key] public ulong Pid { get; set; }
}
