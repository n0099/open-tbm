namespace tbm.Crawler
{
    public interface IParser<TPost, in TPostProtoBuf> where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        public void ParsePosts(CrawlRequestFlag requestFlag, IEnumerable<TPostProtoBuf> inPosts,
            ConcurrentDictionary<ulong, TPost> outPosts, List<User> outUsers);
    }
}
