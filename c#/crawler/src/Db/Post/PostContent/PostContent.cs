// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post.PostContent;

public abstract class PostContent : RowVersionedEntity
{
    public byte[]? ProtoBufBytes { get; set; }
}
