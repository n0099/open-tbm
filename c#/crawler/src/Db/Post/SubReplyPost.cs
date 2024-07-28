// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class SubReplyPost : TimestampedEntity, IPost
{
    public ulong Tid { get; set; }
    public long AuthorUid { get; set; }
    public uint? LastSeenAt { get; set; }
    [Column(TypeName = "bigint")]
    public ulong Pid { get; set; }
    [Key] [Column(TypeName = "bigint")]
    public ulong Spid { get; set; }
    public uint PostedAt { get; set; }
    public int? AgreeCount { get; set; }
    public int? DisagreeCount { get; set; }

    public object Clone() => MemberwiseClone();

    public class Parsed : SubReplyPost, IPostWithContent
    {
        public byte[]? Content { get; set; }
        public required RepeatedField<Content> ContentsProtoBuf { get; set; }
    }
}
