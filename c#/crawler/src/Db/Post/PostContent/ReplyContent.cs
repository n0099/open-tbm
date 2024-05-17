// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post.PostContent;

public class ReplyContent : BasePostContent
{
    [Key] public ulong Pid { get; set; }
}
