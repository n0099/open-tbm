// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db;

public class ThreadMissingFirstReply : RowVersionedEntity
{
    [Key] public ulong Tid { get; set; }
    public ulong? Pid { get; set; }
    public byte[]? Excerpt { get; set; }
    public uint? DiscoveredAt { get; set; }
}
