// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision;

public abstract class ForumScopedRevision : RowVersionedEntity
{
    public uint DiscoveredAt { get; set; }
    public uint Fid { get; set; }
}
