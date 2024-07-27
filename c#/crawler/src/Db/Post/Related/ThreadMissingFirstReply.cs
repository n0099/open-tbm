// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post.Related;

public class ThreadMissingFirstReply : RowVersionedEntity
{
    [Key] [Column(TypeName = "bigint")]
    public ulong Tid { get; set; }
    public ulong? Pid { get; set; }
    public byte[]? Excerpt { get; set; }
    public uint? LastSeenAt { get; set; }
}
