// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class ReplyPost : PostWithAuthorExpGrade
{
    [Key] public ulong Pid { get; set; }
    public uint Floor { get; set; }
    public required ReplyContent Content { get; set; }

    [JsonConverter(typeof(ProtoBufRepeatedFieldJsonConverter<Content>))]
    [NotMapped]
    public required RepeatedField<Content> OriginalContents { get; set; }
    public uint? SubReplyCount { get; set; }
    public uint PostedAt { get; set; }
    public byte? IsFold { get; set; }
    public int? AgreeCount { get; set; }
    public int? DisagreeCount { get; set; }
    public byte[]? Geolocation { get; set; }
    public uint? SignatureId { get; set; }
    [NotMapped] public byte[]? Signature { get; set; }

    public override object Clone() => MemberwiseClone();
}
