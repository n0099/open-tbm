namespace tbm.Crawler.Tieba.Crawl.Crawler
{
    public class ThreadArchiveCrawler : ThreadCrawler
    {
        public new delegate ThreadArchiveCrawler New(string forumName);

        public ThreadArchiveCrawler(ClientRequester requester, string forumName) : base(requester, forumName) { }

        protected override async Task<IEnumerable<Request>> RequestsFactory(Page page)
        {
            var response = await Requester.RequestProtoBuf(EndPointUrl, "6.0.2", ParamDataProp, ParamCommonProp,
                () => new ThreadResponse(), new ThreadRequest {Data = RequestData602Factory(page)});
            return new[]
            { // passing CrawlRequestFlag.Thread602ClientVersion in the second one in order to invokes ThreadParser.ShouldSkipParse()
                new Request(Task.FromResult(response), page),
                new Request(Task.FromResult(response), page, CrawlRequestFlag.Thread602ClientVersion)
            }.AsEnumerable();
        }
    }
}
