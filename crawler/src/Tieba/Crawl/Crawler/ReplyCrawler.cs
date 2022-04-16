using System;
using System.Collections.Generic;
using System.Threading.Tasks;
using TbClient.Api.Request;
using TbClient.Api.Response;
using TbClient.Post;
using Tid = System.UInt64;

namespace tbm.Crawler
{
    public sealed class ReplyCrawler : BaseCrawler<ReplyResponse, Reply>
    {
        private readonly Tid _tid;

        public delegate ReplyCrawler New(Tid tid);

        public ReplyCrawler(ClientRequester requester, Tid tid) : base(requester) => _tid = tid;

        public override Exception FillExceptionData(Exception e)
        {
            e.Data["tid"] = _tid;
            return e;
        }

        public override async Task<(ReplyResponse, CrawlRequestFlag)[]> CrawlSinglePage(uint page) =>
            new[]
            {
                (await Requester.RequestProtoBuf<ReplyRequest, ReplyResponse>(
                    "http://c.tieba.baidu.com/c/f/pb/page?cmd=302001",
                    new ReplyRequest
                    {
                        Data = new ReplyRequest.Types.Data
                        { // reverse order will be {"last", "1"}, {"r", "1"}
                            Kz = (long)_tid,
                            Pn = (int)page,
                            Rn = 30,
                            QType = 2
                        }
                    },
                    "12.23.1.0"
                ), CrawlRequestFlag.None)
            };

        public override IList<Reply> GetValidPosts(ReplyResponse response)
        {
            var error = (TbClient.Error)ReplyResponse.Descriptor.FindFieldByName("error").Accessor.GetValue(response);
            if (error.Errorno == 4)
                throw new TiebaException("Thread already deleted when crawling reply");
            ValidateOtherErrorCode(response);
            return EnsureNonEmptyPostList(response, 6,
                "Reply list is empty, posts might already deleted from tieba");
        }
    }
}
