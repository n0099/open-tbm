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
    public sealed class SubReplyCrawler : BaseCrawler
    {
        protected override CrawlerLocks CrawlerLocks { get; init; }
        private readonly Tid _tid;
        private readonly Pid _pid;

        public delegate ReplyCrawler New(Fid fid, Tid tid, Pid pid);

        public SubReplyCrawler(
            ILogger<SubReplyCrawler> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            IIndex<string, CrawlerLocks.New> locks,
            Fid fid, Tid tid, Pid pid
        ) : base(logger, requesterTcs, requester, fid)
        {
            CrawlerLocks = locks["reply"]("reply");
            _tid = tid;
            _pid = pid;
        }

        protected override void ParseThreads(ArrayEnumerator threads) => throw new System.NotImplementedException();

        protected override ArrayEnumerator ValidateJson(JsonElement json)
        {
            var errorCode = json.GetProperty("error_code").GetString();
            switch (errorCode)
            {
                case "4":
                    throw new Exception("Reply already deleted when crawling sub reply");
                case "28":
                    throw new Exception("Thread already deleted when crawling sub reply");
                default:
                    ValidateOtherErrorCode(json);
                    return CheckIsEmptyPostList(json, "subpost_list",
                        "Sub reply list is empty, posts might already deleted from tieba");
            }
        }

        protected override async Task<JsonElement> CrawlSinglePage(uint page) =>
            await RequestJson("http://c.tieba.baidu.com/c/f/pb/floor", new Dictionary<string, string>
            {
                {"kz", _tid.ToString()},
                {"pid", _pid.ToString()},
                {"pn", page.ToString()}
            });

        protected override Exception FillExceptionData(Exception e)
        {
            e.Data["tid"] = _tid;
            e.Data["pid"] = _pid;
            return e;
        }
    }
}
