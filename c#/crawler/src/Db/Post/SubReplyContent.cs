// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class SubReplyContent : RowVersionedEntity, IPostContent
{
    [Key] public ulong Spid { get; set; }
    public byte[]? ProtoBufBytes { get; set; }
}
