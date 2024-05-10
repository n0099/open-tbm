// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class ReplyContent : RowVersionedEntity, IPostContent
{
    [Key] public ulong Pid { get; set; }
    public byte[]? ProtoBufBytes { get; set; }
}
