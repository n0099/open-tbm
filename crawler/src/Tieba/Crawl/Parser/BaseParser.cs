namespace tbm.Crawler
{
    public abstract class BaseParser<TPost, TPostProtoBuf>
        where TPost: class, IPost where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        protected abstract ulong PostIdSelector(TPost post);
        protected abstract TPost Convert(TPostProtoBuf el);
        protected abstract IEnumerable<TPost> ParsePostsInternal(IEnumerable<TPostProtoBuf> inPosts, List<User> outUsers);
        protected virtual bool TrySkipParse(CrawlRequestFlag requestFlag, IEnumerable<TPostProtoBuf> inPosts, ConcurrentDictionary<ulong, TPost> outPosts) => false;
        protected virtual (TPost First, TPost Last) GetFirstAndLastOfParsed(List<TPost> parsed) => (parsed.First(), parsed.Last());

        public (TPost First, TPost Last)? ParsePosts(
            CrawlRequestFlag requestFlag, IList<TPostProtoBuf> inPosts,
            in ConcurrentDictionary<ulong, TPost> outPosts, out List<User> outUsers)
        {
            outUsers = new();
            if (TrySkipParse(requestFlag, inPosts, outPosts)) return null;

            var parsed = ParsePostsInternal(inPosts, outUsers);
            parsed = parsed.ToList();
            foreach (var p in parsed) outPosts[PostIdSelector(p)] = p;

            return GetFirstAndLastOfParsed((List<TPost>)parsed);
        }
    }
}
