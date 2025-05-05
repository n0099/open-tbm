// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post.PostContent;

public abstract class BasePostContent : RowVersionedEntity
{
    public uint Fid { get; set; }
    public byte[]? ProtoBufBytes { get; set; }
}
