namespace tbm.Crawler.Tieba.Crawl.Crawler
{
    public class ThreadCrawler : BaseCrawler<ThreadResponse, Thread>
    {
        protected override PropertyInfo ParamDataProp => typeof(ThreadRequest).GetProperty(nameof(ThreadRequest.Data))!;
        protected override PropertyInfo ParamCommonProp => ParamDataProp.PropertyType.GetProperty(nameof(ThreadRequest.Data.Common))!;
        protected override PropertyInfo ResponseDataProp => typeof(ThreadResponse).GetProperty(nameof(ThreadResponse.Data))!;
        protected override PropertyInfo ResponsePostListProp => ResponseDataProp.PropertyType.GetProperty(nameof(ThreadResponse.Data.ThreadList))!;
        protected override PropertyInfo ResponsePageProp => ResponseDataProp.PropertyType.GetProperty(nameof(ThreadResponse.Data.Page))!;
        protected override PropertyInfo ResponseErrorProp => typeof(ThreadResponse).GetProperty(nameof(ThreadResponse.Error))!;

        private readonly string _forumName;

        public delegate ThreadCrawler New(string forumName);

        public ThreadCrawler(ClientRequester requester, string forumName) : base(requester) => _forumName = forumName;

        public override Exception FillExceptionData(Exception e)
        {
            e.Data["forumName"] = _forumName;
            return e;
        }

        protected const string EndPointUrl = "c/f/frs/page?cmd=301001";

        protected ThreadRequest.Types.Data GetRequestDataForClientVersion602(Page page) =>
            new()
            {
                Kw = _forumName,
                Pn = (int)page,
                Rn = 30
            };

        protected override Task<IEnumerable<Request>> RequestsFactory(Page page)
        {
            var data602 = GetRequestDataForClientVersion602(page);
            var data = new ThreadRequest.Types.Data
            {
                Kw = _forumName,
                Pn = (int)page,
                Rn = 90,
                RnNeed = 30,
                QType = 2,
                SortType = 5
            };
            return Task.FromResult(new[]
            {
                new Request(Requester.RequestProtoBuf(EndPointUrl, "12.26.1.0", ParamDataProp, ParamCommonProp, () => new ThreadResponse(),
                    new ThreadRequest {Data = data}), page),
                new Request(Requester.RequestProtoBuf(EndPointUrl, "6.0.2", ParamDataProp, ParamCommonProp, () => new ThreadResponse(),
                    new ThreadRequest {Data = data602}), page, CrawlRequestFlag.ThreadClientVersion602),
                new Request(RequestJsonForFirstPid(page), page, CrawlRequestFlag.ThreadClientVersion8888)
            }.AsEnumerable());
        }

        private async Task<ThreadResponse> RequestJsonForFirstPid(Page page)
        {
            var json = await Requester.RequestJson("c/f/frs/page", "8.8.8.8", new()
            {
                {"kw", _forumName},
                {"pn", page.ToString()},
                {"rn", "30"}
            });
            try
            {
                return new()
                {
                    Error = new() {Errorno = 0},
                    Data = new()
                    {
                        ThreadList =
                        {
                            json.GetProperty("thread_list").EnumerateArray().Select(el => new Thread
                            {
                                Tid = el.GetProperty("id").GetInt64(),
                                FirstPostId = el.GetProperty("first_post_id").GetInt64()
                            })
                        }
                    }
                };
            }
            catch (Exception e) when (e is not TiebaException)
            {
                e.Data["raw"] = json;
                throw;
            }
        }

        public override IList<Thread> GetValidPosts(ThreadResponse response, CrawlRequestFlag flag)
        {
            ValidateOtherErrorCode(response);
            return EnsureNonEmptyPostList(response, "Forum threads list is empty, forum might doesn't existed.");
        }
    }
}
