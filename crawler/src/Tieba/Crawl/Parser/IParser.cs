using System.Collections.Concurrent;
using System.Collections.Generic;
using Google.Protobuf;

namespace tbm.Crawler
{
    public interface IParser<TPost, in TPostProtoBuf> where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        public void ParsePosts(CrawlRequestFlag requestFlag, IEnumerable<TPostProtoBuf> inPosts,
            ConcurrentDictionary<ulong, TPost> outPosts, UserParserAndSaver userParser);
        public static string? RawJsonOrNullWhenEmpty(string json) => json is @"""""" or "[]" ? null : json;
    }
}
