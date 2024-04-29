// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision;

public class ForumModeratorRevision
{
    public uint DiscoveredAt { get; set; }
    public uint Fid { get; set; }
    public required string Portrait { get; set; }
    public required string ModeratorTypes { get; set; }
}
