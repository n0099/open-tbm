// ReSharper disable PropertyCanBeMadeInitOnly.Global
using System.ComponentModel.DataAnnotations.Schema;

namespace tbm.Shared.Db;

public class ReplyContentImage : EntityWithImageId
{
    [Column(TypeName = "bigint")]
    public ulong Pid { get; set; }
    public required ImageInReply ImageInReply { get; set; }
}
