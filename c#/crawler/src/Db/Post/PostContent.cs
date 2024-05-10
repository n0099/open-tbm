// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public abstract class PostContent : RowVersionedEntity
{
    public byte[]? ProtoBufBytes { get; set; }
}
