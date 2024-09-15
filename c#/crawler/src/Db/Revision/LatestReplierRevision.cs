// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision;

public class LatestReplierRevision
{
    public uint TakenAt { get; set; }
    public uint Id { get; set; }
    public long Uid { get; set; }
    public string? Name { get; set; }
    public string? DisplayName { get; set; }
}
