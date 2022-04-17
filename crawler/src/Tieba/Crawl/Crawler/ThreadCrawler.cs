using System;
using System.Collections.Generic;
using System.Threading.Tasks;
using TbClient.Api.Request;
using TbClient.Api.Response;
using TbClient.Post;

namespace tbm.Crawler
{
    public sealed class ThreadCrawler : BaseCrawler<ThreadResponse, Thread>
    {
        private readonly string _forumName;

        public delegate ThreadCrawler New(string forumName);

        public ThreadCrawler(ClientRequester requester, string forumName) : base(requester) => _forumName = forumName;

        public override Exception FillExceptionData(Exception e)
        {
            e.Data["forumName"] = _forumName;
            return e;
        }

        public override Task<(ThreadResponse, CrawlRequestFlag)[]> CrawlSinglePage(uint page)
        {
            const string url = "http://c.tieba.baidu.com/c/f/frs/page?cmd=301001";
            var requestBody602 = new ThreadRequest.Types.Data
            {
                Kw = _forumName,
                Pn = (int)page,
                Rn = 30
            };
            var requestBody = new ThreadRequest.Types.Data
            {
                Kw = _forumName,
                Pn = (int)page,
                Rn = 90,
                RnNeed = 30,
                QType = 2,
                SortType = 5
            };
            return Task.WhenAll(
                Requester.RequestProtoBuf<ThreadRequest, ThreadResponse>(url, new ThreadRequest {Data = requestBody}, "12.23.1.0")
                    .ContinueWith(t => (t.Result, CrawlRequestFlag.None)),
                Requester.RequestProtoBuf<ThreadRequest, ThreadResponse>(url, new ThreadRequest {Data = requestBody602}, "6.0.2")
                    .ContinueWith(t => (t.Result, CrawlRequestFlag.Thread602ClientVersion))
            );
        }

        public override IList<Thread> GetValidPosts(ThreadResponse response)
        {
            ValidateOtherErrorCode(response);
            return EnsureNonEmptyPostList(response, 7,
                "Forum threads list is empty, forum might doesn't existed");
        }
    }
}
