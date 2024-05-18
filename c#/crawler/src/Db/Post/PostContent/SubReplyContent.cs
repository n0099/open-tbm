// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post.PostContent;

public class SubReplyContent : BasePostContent
{
    [Key] [Column(TypeName = "bigint")]
    public ulong Spid { get; set; }
}
