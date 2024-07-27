namespace tbm.Crawler.Tieba.Crawl.Parser.Post;

public interface IPostParser<TPost, in TPostProtoBuf>
    where TPost : IPost.IParsed
    where TPostProtoBuf : class, IMessage<TPostProtoBuf>
{
    public void Parse(
        CrawlRequestFlag requestFlag,
        IReadOnlyCollection<TPostProtoBuf> inPosts,
        out IReadOnlyDictionary<PostId, TPost> outPosts,
        out IReadOnlyCollection<TbClient.User> outUsers);
}
