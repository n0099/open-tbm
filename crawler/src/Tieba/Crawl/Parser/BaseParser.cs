namespace tbm.Crawler.Tieba.Crawl.Parser
{
    public abstract class BaseParser<TPost, TPostProtoBuf>
        where TPost: class, IPost
        where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        protected abstract PostId PostIdSelector(TPost post);
        protected abstract TPost Convert(TPostProtoBuf inPost);
        protected abstract IEnumerable<TPost> ParsePostsInternal(IList<TPostProtoBuf> inPosts, List<User?> outUsers);
        protected virtual bool ShouldSkipParse(CrawlRequestFlag requestFlag,
            IEnumerable<TPostProtoBuf> inPosts, ConcurrentDictionary<PostId, TPost> outPosts) => false;

        public void ParsePosts(CrawlRequestFlag requestFlag, IList<TPostProtoBuf> inPosts,
            in ConcurrentDictionary<PostId, TPost> outPosts, out List<User> outUsers)
        {
            outUsers = new(30);
            var outNullableUsers = new List<User?>();
            if (ShouldSkipParse(requestFlag, inPosts, outPosts)) return;
            foreach (var p in ParsePostsInternal(inPosts, outNullableUsers))
                outPosts[PostIdSelector(p)] = p;
            outUsers.AddRange(outNullableUsers.OfType<User>()
                .Where(u => u.CalculateSize() != 0)); // remove empty users
        }
    }
}
