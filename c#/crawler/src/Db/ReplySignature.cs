// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db;

public class ReplySignature
{
    public long UserId { get; set; }
    public uint SignatureId { get; set; }
    public required byte[] XxHash3 { get; set; }
    public required byte[] ProtoBufBytes { get; set; }
    public uint FirstSeenAt { get; set; }
    public uint LastSeenAt { get; set; }
}
