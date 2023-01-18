// ReSharper disable UnusedAutoPropertyAccessor.Global
// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db;

public class ReplySignature
{
    public long UserId { get; set; }
    public uint SignatureId { get; set; }
    public byte[] SignatureMd5 { get; set; } = null!;
    public byte[] Signature { get; set; } = null!;
    public uint FirstSeenAt { get; set; }
    public uint LastSeenAt { get; set; }
}
