// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post.PostContent;

public class SubReplyContent : BasePostContent
{
    [Key] public ulong Spid { get; set; }
}
