// ReSharper disable PropertyCanBeMadeInitOnly.Global
// ReSharper disable UnusedAutoPropertyAccessor.Global
namespace tbm.Crawler.Db.Revision;

public abstract class AuthorRevision : RowVersionedEntity
{
    public uint DiscoveredAt { get; set; }
    public uint Fid { get; set; }
    public long Uid { get; set; }
    public required PostType TriggeredBy { get; set; }
}
