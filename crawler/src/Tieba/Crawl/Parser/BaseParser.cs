namespace tbm.Crawler
{
    public abstract class BaseParser<TPost, TPostProtoBuf>
        where TPost: class, IPost where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        public (TPost First, TPost Last)? ParsePosts(
            CrawlRequestFlag requestFlag, IEnumerable<TPostProtoBuf> inPosts,
            in ConcurrentDictionary<ulong, TPost> outPosts, out List<User> outUsers)
        {
            outUsers = new();
            var parsedTuple = ParsePostsInternal(requestFlag, inPosts, outPosts, outUsers);
            if (parsedTuple == null) return null;
            var (parsed, postIdSelector) = parsedTuple.Value;
            var parsedPosts = parsed.ToList();
            foreach (var p in parsedPosts) outPosts[postIdSelector(p)] = p;

            if (parsedPosts is List<ThreadPost> threads)
            { // filter out sticky threads
                var parsedThreads = threads.Where(t => t.StickyType == null).ToList();
                return ((TPost, TPost))((IPost, IPost))(parsedThreads.First(), parsedThreads.Last());
            }
            return (parsedPosts.First(), parsedPosts.Last());
        }

        protected abstract (IEnumerable<TPost> parsed, Func<TPost, ulong> postIdSelector)? ParsePostsInternal(
            CrawlRequestFlag requestFlag, IEnumerable<TPostProtoBuf> inPosts,
            ConcurrentDictionary<ulong, TPost> outPosts, List<User> outUsers);
    }
}
