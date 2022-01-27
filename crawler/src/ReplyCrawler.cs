using System;
using System.Collections.Generic;
using System.Text.Json;
using System.Threading.Tasks;
using Autofac.Features.Indexed;
using Microsoft.Extensions.Logging;
using static System.Text.Json.JsonElement;
using Fid = System.UInt32;
using Tid = System.UInt64;
using Pid = System.UInt64;

namespace tbm
{
    public sealed class ReplyCrawler : BaseCrawler
    {
        protected override CrawlerLocks CrawlerLocks { get; init; }
        private readonly Tid _tid;

        public delegate ReplyCrawler New(Fid fid, Tid tid);

        public ReplyCrawler(
            ILogger<ReplyCrawler> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            IIndex<string, CrawlerLocks.New> locks,
            Fid fid, Tid tid
        ) : base(logger, requesterTcs, requester, fid)
        {
            CrawlerLocks = locks["reply"]("reply");
            _tid = tid;
        }

        protected override void ParseThreads(ArrayEnumerator threads) => throw new System.NotImplementedException();

        protected override ArrayEnumerator ValidateJson(JsonElement json)
        {
            var errorCode = json.GetProperty("error_code").GetString();
            if (errorCode == "4")
                throw new Exception("Thread already deleted when crawling reply");
            ValidateOtherErrorCode(json);
            return CheckIsEmptyPostList(json, "post_list",
                "Reply list is empty, posts might already deleted from tieba");
        }

        protected override async Task<JsonElement> CrawlSinglePage(uint page) =>
            await RequestJson("http://c.tieba.baidu.com/c/f/pb/page", new Dictionary<string, string>
            { // reverse order will be {"last", "1"}, {"r", "1"}
                {"kz", _tid.ToString()},
                {"pn", page.ToString()}
            });

        protected override Exception FillExceptionData(Exception e)
        {
            e.Data["tid"] = _tid;
            return e;
        }
    }
}
