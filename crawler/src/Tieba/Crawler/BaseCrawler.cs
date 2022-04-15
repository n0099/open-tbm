using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using Google.Protobuf;
using TbClient.Api.Response;
using Page = System.UInt32;

namespace tbm.Crawler
{
    public abstract class BaseCrawler<TResponse, TPostProtoBuf>
        where TResponse : IMessage<TResponse> where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        protected ClientRequester Requester { get; }

        public abstract Exception FillExceptionData(Exception e);
        public abstract Task<(TResponse, CrawlRequestFlag)[]> CrawlSinglePage(Page page);
        public abstract IList<TPostProtoBuf> GetValidPosts(TResponse response);

        protected BaseCrawler(ClientRequester requester) => Requester = requester;

        protected static void ValidateOtherErrorCode(TResponse response)
        {
            var error = (TbClient.Error)response.Descriptor.FindFieldByName("error").Accessor.GetValue(response);
            if (error.Errorno != 0)
                throw new TiebaException($"Error from tieba client, raw json: {response}");
        }

        protected static IList<TPostProtoBuf> EnsureNonEmptyPostList(TResponse response, int fieldNum, string exceptionMessage)
        {
            var data = (IMessage)response.Descriptor.FindFieldByName("data").Accessor.GetValue(response);
            var posts = (IList<TPostProtoBuf>)data.Descriptor.FindFieldByNumber(fieldNum).Accessor.GetValue(data);
            return posts.Any() ? posts : throw new TiebaException(exceptionMessage);
        }
    }
}
