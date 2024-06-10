// ReSharper disable PropertyCanBeMadeInitOnly.Global
// ReSharper disable UnusedAutoPropertyAccessor.Global
namespace tbm.Crawler.Db.Post;

#pragma warning disable AV1000 // Type name contains the word 'and', which suggests it has multiple purposes
public abstract class PostWithContentAndAuthorExpGrade : PostWithAuthorExpGrade
#pragma warning restore AV1000 // Type name contains the word 'and', which suggests it has multiple purposes
{
    [NotMapped] public byte[]? Content { get; set; }

    [JsonConverter(typeof(ProtoBufRepeatedFieldJsonConverter<Content>))]
    [NotMapped]
    public required RepeatedField<Content> ContentsProtoBuf { get; set; }
}
