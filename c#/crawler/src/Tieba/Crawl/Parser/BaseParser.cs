namespace tbm.Crawler.Tieba.Crawl.Parser;

public abstract class BaseParser<TPost, TPostProtoBuf>
    where TPost : class, IPost
    where TPostProtoBuf : class, IMessage<TPostProtoBuf>
{
    public void ParsePosts(
        CrawlRequestFlag requestFlag, IList<TPostProtoBuf> inPosts,
        out Dictionary<PostId, TPost> outPosts, out List<TbClient.User> outUsers)
    {
        outUsers = new(30);
        if (ShouldSkipParse(requestFlag))
        {
            outPosts = [];
            return;
        }
        var outNullableUsers = new List<TbClient.User?>();
        outPosts = ParsePostsInternal(inPosts, outNullableUsers).ToDictionary(PostIdSelector, post => post);
        if (outPosts.Values.Any(p => p.AuthorUid == 0))
            throw new TiebaException(shouldRetry: true,
                "Value of IPost.AuthorUid is the protoBuf default value 0.");
        outUsers.AddRange(outNullableUsers.OfType<TbClient.User>()
            .Where(u => u.CalculateSize() != 0)); // remove empty users
    }

    protected abstract PostId PostIdSelector(TPost post);
    protected abstract TPost Convert(TPostProtoBuf inPost);
    protected abstract IEnumerable<TPost> ParsePostsInternal(IList<TPostProtoBuf> inPosts, List<TbClient.User?> outUsers);
    protected virtual bool ShouldSkipParse(CrawlRequestFlag requestFlag) => false;
}
