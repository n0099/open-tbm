// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public abstract class PostWithContentAndAuthorExpGrade<TPostContent> : PostWithAuthorExpGrade
    where TPostContent : BasePostContent
{
    public required TPostContent Content { get; set; }

    [JsonConverter(typeof(ProtoBufRepeatedFieldJsonConverter<Content>))]
    [NotMapped]
    public required RepeatedField<Content> ContentsProtoBuf { get; set; }
}
