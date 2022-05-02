namespace tbm.Crawler
{
    public abstract class BaseParser<TPost, TPostProtoBuf>
        where TPost: class, IPost where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        public (TPost First, TPost Last)? ParsePosts(
            CrawlRequestFlag requestFlag, IEnumerable<TPostProtoBuf> inPosts,
            in ConcurrentDictionary<ulong, TPost> outPosts, in List<User> outUsers)
        {
            var parsedTuple = ParsePostsInternal(requestFlag, inPosts, outPosts, outUsers);
            if (parsedTuple == null) return null;
            var (parsed, postIdSelector) = parsedTuple.Value;
            var parsedPosts = parsed.ToList();
            foreach (var p in parsedPosts) outPosts[postIdSelector(p)] = p;

            IEnumerable<TPost> returnPair = parsedPosts;
            if (returnPair is List<ThreadPost> threads)
            { // filter out sticky threads
                returnPair = (IEnumerable<TPost>)threads.Where(t => t.StickyType == null);
            }
            // ReSharper disable PossibleMultipleEnumeration
            return (returnPair.First(), returnPair.Last());
            // ReSharper restore PossibleMultipleEnumeration
        }

        protected abstract (IEnumerable<TPost> parsed, Func<TPost, ulong> postIdSelector)? ParsePostsInternal(
            CrawlRequestFlag requestFlag, IEnumerable<TPostProtoBuf> inPosts,
            ConcurrentDictionary<ulong, TPost> outPosts, List<User> outUsers);
    }
}
