// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class ReplyPost : TimestampedEntity, IPost
{
    public ulong Tid { get; set; }
    public long AuthorUid { get; set; }
    public uint? LastSeenAt { get; set; }
    [Key] [Column(TypeName = "bigint")]
    public ulong Pid { get; set; }
    public uint Floor { get; set; }
    public uint? SubReplyCount { get; set; }
    public uint PostedAt { get; set; }
    public byte? IsFold { get; set; }
    public int? AgreeCount { get; set; }
    public int? DisagreeCount { get; set; }
    public byte[]? Geolocation { get; set; }
    public uint? SignatureId { get; set; }

    public object Clone() => MemberwiseClone();

    public class Parsed : ReplyPost, IPostWithContent
    {
        public byte[]? Content { get; set; }
        public required RepeatedField<Content> ContentsProtoBuf { get; set; }
        public byte[]? Signature { get; set; }
    }
}
