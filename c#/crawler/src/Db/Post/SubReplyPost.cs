// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class SubReplyPost : PostWithAuthorExpGrade
{
    public ulong Pid { get; set; }
    [Key] public ulong Spid { get; set; }
    [NotMapped] public byte[]? Content { get; set; }

    [JsonConverter(typeof(ProtoBufRepeatedFieldJsonConverter<Content>))]
    [NotMapped]
    public required RepeatedField<Content> OriginalContents { get; set; }
    public uint PostedAt { get; set; }
    public int? AgreeCount { get; set; }
    public int? DisagreeCount { get; set; }

    public override object Clone() => MemberwiseClone();
}
