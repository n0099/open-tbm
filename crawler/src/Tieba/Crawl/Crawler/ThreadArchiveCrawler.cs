namespace tbm.Crawler.Tieba.Crawl.Crawler
{
    public class ThreadArchiveCrawler : ThreadCrawler
    {
        public new delegate ThreadArchiveCrawler New(string forumName);

        public ThreadArchiveCrawler(ClientRequester requester, string forumName) : base(requester, forumName) { }

        protected override async Task<IEnumerable<Request>> RequestsFactory(Page page)
        {
            var response = await Requester.RequestProtoBuf(EndPointUrl, "6.0.2", ParamDataProp, ParamCommonProp,
                () => new ThreadResponse(), new ThreadRequest {Data = GetRequestDataForClientVersion602(page)});
            return new[]
            { // passing CrawlRequestFlag.ThreadClientVersion602 in the second one in order to invokes ThreadParser.ShouldSkipParse()
                new Request(Task.FromResult(response), page),
                new Request(Task.FromResult(response), page, CrawlRequestFlag.ThreadClientVersion602)
            }.AsEnumerable();
        }
    }
}
