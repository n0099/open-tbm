namespace tbm.Crawler
{
    public abstract class BaseParser<TPost, TPostProtoBuf>
        where TPost: class, IPost where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        protected abstract IEnumerable<TPost>? ParsePostsInternal(
            CrawlRequestFlag requestFlag, IEnumerable<TPostProtoBuf> inPosts,
            ConcurrentDictionary<ulong, TPost> outPosts, List<User> outUsers);

        protected abstract ulong PostIdSelector(TPost post);

        public (TPost First, TPost Last)? ParsePosts(
            CrawlRequestFlag requestFlag, IEnumerable<TPostProtoBuf> inPosts,
            in ConcurrentDictionary<ulong, TPost> outPosts, out List<User> outUsers)
        {
            outUsers = new();
            var parsed = ParsePostsInternal(requestFlag, inPosts, outPosts, outUsers);
            if (parsed == null) return null;
            parsed = parsed.ToList();
            foreach (var p in parsed) outPosts[PostIdSelector(p)] = p;

            if (parsed is List<ThreadPost> threads)
            { // filter out sticky threads
                var parsedThreads = threads.Where(t => t.StickyType == null).ToList();
                return ((TPost, TPost))((IPost, IPost))(parsedThreads.First(), parsedThreads.Last());
            }
            return (parsed.First(), parsed.Last());
        }
    }
}
