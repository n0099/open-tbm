namespace tbm.Crawler.Tieba.Crawl.Crawler
{
    public class ThreadArchiveCrawler : ThreadCrawler
    {
        public new delegate ThreadArchiveCrawler New(string forumName);

        public ThreadArchiveCrawler(ClientRequester requester, string forumName) : base(requester, forumName) { }

        protected override async Task<IEnumerable<Request>> RequestsFactory(Page page)
        {
            var response = await Requester.RequestProtoBuf(EndPointUrl, "6.0.2",
                new ThreadRequest {Data = GetRequestDataForClientVersion602(page)},
                (req, common) => req.Data.Common = common,
                () => new ThreadResponse());
            return new[]
            { // passing CrawlRequestFlag.ThreadClientVersion602 in the second one in order to invokes ThreadParser.ShouldSkipParse()
                new Request(Task.FromResult(response)),
                new Request(Task.FromResult(response), CrawlRequestFlag.ThreadClientVersion602)
            }.AsEnumerable();
        }
    }
}
