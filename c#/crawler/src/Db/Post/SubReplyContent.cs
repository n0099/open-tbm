// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class SubReplyContent : PostContent
{
    [Key] public ulong Spid { get; set; }
}
