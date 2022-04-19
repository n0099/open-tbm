using System.Collections.Concurrent;
using System.Collections.Generic;
using Google.Protobuf;
using TbClient;

namespace tbm.Crawler
{
    public interface IParser<TPost, in TPostProtoBuf> where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        public List<User>? ParsePosts(CrawlRequestFlag requestFlag,
            IEnumerable<TPostProtoBuf> inPosts, ConcurrentDictionary<ulong, TPost> outPosts);
    }
}
