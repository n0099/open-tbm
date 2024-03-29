// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class SubReplyPost : IPost, IPostWithAuthorExpGrade
{
    public ulong Tid { get; set; }
    public ulong Pid { get; set; }
    [Key] public ulong Spid { get; set; }
    [NotMapped] public byte[]? Content { get; set; }

    [JsonConverter(typeof(ProtoBufRepeatedFieldJsonConverter<Content>))]
    [NotMapped]
    public required RepeatedField<Content> OriginalContents { get; set; }
    public long AuthorUid { get; set; }
    [NotMapped] public byte AuthorExpGrade { get; set; }
    public uint PostedAt { get; set; }
    public int? AgreeCount { get; set; }
    public int? DisagreeCount { get; set; }
    public uint CreatedAt { get; set; }
    public uint? UpdatedAt { get; set; }
    public uint? LastSeenAt { get; set; }

    public object Clone() => MemberwiseClone();
}
