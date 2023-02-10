namespace tbm.Crawler.Tieba.Crawl.Parser;

public abstract class BaseParser<TPost, TPostProtoBuf>
    where TPost: class, IPost
    where TPostProtoBuf : IMessage<TPostProtoBuf>
{
    protected abstract PostId PostIdSelector(TPost post);
    protected abstract TPost Convert(TPostProtoBuf inPost);
    protected abstract IEnumerable<TPost> ParsePostsInternal(IList<TPostProtoBuf> inPosts, List<User?> outUsers);
    protected virtual bool ShouldSkipParse(CrawlRequestFlag requestFlag) => false;

    public void ParsePosts(CrawlRequestFlag requestFlag, IList<TPostProtoBuf> inPosts,
        out Dictionary<PostId, TPost> outPosts, out List<User> outUsers)
    {
        outUsers = new(30);
        if (ShouldSkipParse(requestFlag))
        {
            outPosts = new();
            return;
        }
        var outNullableUsers = new List<User?>();
        outPosts = ParsePostsInternal(inPosts, outNullableUsers)
            .Select(post => (Id: PostIdSelector(post), post))
            .ToDictionary(t => t.Id, t => t.post);
        outUsers.AddRange(outNullableUsers.OfType<User>()
            .Where(u => u.CalculateSize() != 0)); // remove empty users
    }
}
