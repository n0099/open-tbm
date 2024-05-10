// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Shared.Db;

public class ReplyContentImage : EntityWithImageId
{
    public ulong Pid { get; set; }
    public required ImageInReply ImageInReply { get; set; }
}
