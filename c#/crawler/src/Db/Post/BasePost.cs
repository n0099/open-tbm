// ReSharper disable UnusedMemberInSuper.Global
// ReSharper disable UnusedAutoPropertyAccessor.Global
namespace tbm.Crawler.Db.Post;

public abstract class BasePost : TimestampedEntity, ICloneable
{
    [Column(TypeName = "bigint")]
    public ulong Tid { get; set; }
    public long AuthorUid { get; set; }
    public uint? LastSeenAt { get; set; }
    public abstract object Clone();
}
