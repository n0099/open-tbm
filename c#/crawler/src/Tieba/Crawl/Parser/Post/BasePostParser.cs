namespace tbm.Crawler.Tieba.Crawl.Parser.Post;

public abstract class BasePostParser<TPost, TPostProtoBuf>
    where TPost : BasePost
    where TPostProtoBuf : class, IMessage<TPostProtoBuf>
{
    public void Parse(
        CrawlRequestFlag requestFlag, IReadOnlyCollection<TPostProtoBuf> inPosts,
        out IReadOnlyDictionary<PostId, TPost> outPosts, out IReadOnlyCollection<TbClient.User> outUsers)
    {
        if (ShouldSkipParse(requestFlag))
        {
            outPosts = new Dictionary<PostId, TPost>();
            outUsers = [];
            return;
        }
        var nullableUsers = new List<TbClient.User?>();
        outPosts = ParseInternal(inPosts, nullableUsers).ToDictionary(PostIdSelector, post => post);
        if (outPosts.Values.Any(p => p.AuthorUid == 0))
            throw new TiebaException(shouldRetry: true,
                "Value of BasePost.AuthorUid is the protoBuf default value 0.");

        var users = new List<TbClient.User>(30);
        users.AddRange(nullableUsers.OfType<TbClient.User>()
            .Where(u => u.CalculateSize() != 0)); // remove empty users
        outUsers = users;
    }

    // ReSharper disable once UnusedMemberInSuper.Global
    protected abstract TPost Convert(TPostProtoBuf inPost);
    protected abstract IEnumerable<TPost> ParseInternal
        (IReadOnlyCollection<TPostProtoBuf> inPosts, ICollection<TbClient.User?> outUsers);
    protected virtual bool ShouldSkipParse(CrawlRequestFlag requestFlag) => false;
    protected abstract PostId PostIdSelector(TPost post);
}
