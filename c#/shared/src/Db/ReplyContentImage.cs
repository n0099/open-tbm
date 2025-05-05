// ReSharper disable PropertyCanBeMadeInitOnly.Global
using System.ComponentModel.DataAnnotations.Schema;

namespace tbm.Shared.Db;

public class ReplyContentImage : EntityWithImageId
{
    public uint Fid { get; set; }
    [Column(TypeName = "bigint")]
    public ulong Pid { get; set; }
    public required ImageInReply ImageInReply { get; set; }
}
