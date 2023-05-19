namespace tbm.Crawler.Tieba.Crawl.Crawler;

public class ThreadArchiveCrawler : ThreadCrawler
{
    public new delegate ThreadArchiveCrawler New(string forumName);

    public ThreadArchiveCrawler(ClientRequester requester, string forumName) : base(requester, forumName) { }

    protected override IEnumerable<Request> GetRequestsForPage(Page page, CancellationToken stoppingToken = default)
    {
        var response = Requester.RequestProtoBuf(EndPointUrl, "6.0.2",
            new ThreadRequest {Data = GetRequestDataForClientVersion602(page)},
            (req, common) => req.Data.Common = common,
            () => new ThreadResponse(), stoppingToken);
        return new[]
        { // passing CrawlRequestFlag.ThreadClientVersion602 in the second one in order to invokes ThreadParser.ShouldSkipParse()
            new Request(response),
            new Request(response, CrawlRequestFlag.ThreadClientVersion602)
        };
    }
}
