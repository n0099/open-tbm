// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public abstract class PostWithContentAndAuthorExpGrade : PostWithAuthorExpGrade
{
    [NotMapped] public byte[]? Content { get; set; }

    [JsonConverter(typeof(ProtoBufRepeatedFieldJsonConverter<Content>))]
    [NotMapped]
    public required RepeatedField<Content> ContentsProtoBuf { get; set; }
}
