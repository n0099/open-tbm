// ReSharper disable UnusedMemberInSuper.Global
// ReSharper disable UnusedAutoPropertyAccessor.Global
namespace tbm.Crawler.Db.Post;

public interface IPost : ICloneable
{
    public interface IParsed : IPost;
    [Column(TypeName = "bigint")]
    public ulong Tid { get; set; }
    public long AuthorUid { get; set; }
    public uint? LastSeenAt { get; set; }
}
