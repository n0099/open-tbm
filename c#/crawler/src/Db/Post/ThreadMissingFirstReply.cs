// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class ThreadMissingFirstReply : RowVersionedEntity
{
    [Key] public ulong Tid { get; set; }
    public ulong? Pid { get; set; }
    public byte[]? Excerpt { get; set; }
    public uint? LastSeenAt { get; set; }
}
