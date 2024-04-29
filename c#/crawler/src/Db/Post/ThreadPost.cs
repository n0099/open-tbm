// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class ThreadPost : IPost
{
    [Key] public ulong Tid { get; set; }
    [NotMapped] public ulong? FirstReplyPid { get; set; }

    [JsonConverter(typeof(ProtoBufRepeatedFieldJsonConverter<Abstract>))]
    [NotMapped]
    public RepeatedField<Abstract>? FirstReplyExcerpt { get; set; }
    public ulong ThreadType { get; set; }
    public string? StickyType { get; set; }
    public string? TopicType { get; set; }
    public byte? IsGood { get; set; }
    public required string Title { get; set; }
    public long AuthorUid { get; set; }
    public string? AuthorPhoneType { get; set; }
    public uint PostedAt { get; set; }
    public uint LatestReplyPostedAt { get; set; }
    public long? LatestReplierUid { get; set; }
    public uint? ReplyCount { get; set; }
    public uint? ViewCount { get; set; }
    public uint? ShareCount { get; set; }
    public int? AgreeCount { get; set; }
    public int? DisagreeCount { get; set; }
    public byte[]? Zan { get; set; }
    public byte[]? Geolocation { get; set; }
    public uint CreatedAt { get; set; }
    public uint? UpdatedAt { get; set; }
    public uint? LastSeenAt { get; set; }

    public object Clone() => MemberwiseClone();
}
