namespace tbm.Crawler.Db.Post;

public interface IPostWithContent : IPost.IParsed
{
    public byte[]? Content { get; set; }
    [JsonConverter(typeof(ProtoBufRepeatedFieldJsonConverter<Content>))]
    public RepeatedField<Content> ContentsProtoBuf { get; set; }
}
