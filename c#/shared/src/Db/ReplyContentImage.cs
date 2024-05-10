// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Shared.Db;

public class ReplyContentImage
{
    public ulong Pid { get; set; }
    public uint ImageId { get; set; }
    public required ImageInReply ImageInReply { get; set; }
}
