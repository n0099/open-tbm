// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class ReplySignature : RowVersionedEntity
{
    public long Uid { get; set; }
    public uint SignatureId { get; set; }
    public required byte[] XxHash3 { get; set; }
    public required byte[] ProtoBufBytes { get; set; }
    public uint FirstSeenAt { get; set; }
    public uint LastSeenAt { get; set; }
}
