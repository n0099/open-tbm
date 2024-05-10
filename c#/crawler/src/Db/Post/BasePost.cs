// ReSharper disable UnusedMemberInSuper.Global
namespace tbm.Crawler.Db.Post;

public abstract class BasePost : TimestampedEntity, ICloneable
{
    public ulong Tid { get; set; }
    public long AuthorUid { get; set; }
    public uint? LastSeenAt { get; set; }
    public abstract object Clone();
}
