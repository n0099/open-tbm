namespace tbm.Crawler.Tieba.Crawl.Parser;

public abstract class BaseParser<TPost, TPostProtoBuf>
    where TPost : class, IPost
    where TPostProtoBuf : class, IMessage<TPostProtoBuf>
{
    public void ParsePosts(
        CrawlRequestFlag requestFlag, IList<TPostProtoBuf> inPosts,
        out IDictionary<PostId, TPost> outPosts, out IList<TbClient.User> outUsers)
    {
        if (ShouldSkipParse(requestFlag))
        {
            outPosts = new Dictionary<PostId, TPost>();
            outUsers = [];
            return;
        }
        var nullableUsers = new List<TbClient.User?>();
        outPosts = ParsePostsInternal(inPosts, nullableUsers).ToDictionary(PostIdSelector, post => post);
        if (outPosts.Values.Any(p => p.AuthorUid == 0))
            throw new TiebaException(shouldRetry: true,
                "Value of IPost.AuthorUid is the protoBuf default value 0.");

        var users = new List<TbClient.User>(30);
        users.AddRange(nullableUsers.OfType<TbClient.User>()
            .Where(u => u.CalculateSize() != 0)); // remove empty users
        outUsers = users;
    }

    // ReSharper disable once UnusedMemberInSuper.Global
    protected abstract TPost Convert(TPostProtoBuf inPost);
    protected abstract IEnumerable<TPost> ParsePostsInternal
        (IList<TPostProtoBuf> inPosts, IList<TbClient.User?> outUsers);
    protected virtual bool ShouldSkipParse(CrawlRequestFlag requestFlag) => false;
    protected abstract PostId PostIdSelector(TPost post);
}
