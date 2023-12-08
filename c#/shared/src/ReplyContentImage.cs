// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Shared;

public class ReplyContentImage
{
    public ulong Pid { get; set; }
    public uint ImageId { get; set; }
    public required ImageInReply ImageInReply { get; set; }
}
