namespace tbm.Crawler
{
    public abstract class BaseParser<TPost, TPostProtoBuf> where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        public (TPost First, TPost Last)? ParsePosts(
            CrawlRequestFlag requestFlag, IEnumerable<TPostProtoBuf> inPosts,
            ConcurrentDictionary<ulong, TPost> outPosts, List<User> outUsers)
        {
            var pair = ParsePostsInternal(requestFlag, inPosts, outPosts, outUsers);
            if (pair == null) return null;
            var (parsed, postIdSelector) = pair.Value;
            var parsedList = parsed.ToList();
            foreach (var p in parsedList) outPosts[postIdSelector(p)] = p;

            IEnumerable<TPost> returnPair = parsedList;
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
