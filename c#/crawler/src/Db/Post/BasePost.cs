// ReSharper disable UnusedMemberInSuper.Global
// ReSharper disable UnusedAutoPropertyAccessor.Global
namespace tbm.Crawler.Db.Post;

public interface BasePost : ICloneable
{
    public interface IParsed : BasePost;
    [Column(TypeName = "bigint")]
    public ulong Tid { get; set; }
    public long AuthorUid { get; set; }
    public uint? LastSeenAt { get; set; }
}
