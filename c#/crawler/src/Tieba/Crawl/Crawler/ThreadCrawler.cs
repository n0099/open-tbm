namespace tbm.Crawler.Tieba.Crawl.Crawler;

public class ThreadCrawler(ClientRequester requester, string forumName)
    : BaseCrawler<ThreadResponse, Thread>(requester)
{
    protected const string LegacyEndPointUrl = $"{ClientRequester.LegacyClientApiDomain}/{EndPointUrl}";
    private const string EndPointUrl = "c/f/frs/page?cmd=301001";

    public delegate ThreadCrawler New(string forumName);

    public override Exception FillExceptionData(Exception e)
    {
        e.Data["forumName"] = forumName;
        return e;
    }

    public override IList<Thread> GetValidPosts(ThreadResponse response, CrawlRequestFlag flag)
    {
        ValidateOtherErrorCode(response);
        return EnsureNonEmptyPostList(response,
            "Forum threads list is empty, forum might doesn't existed.");
    }
    public override TbClient.Page? GetResponsePage(ThreadResponse response) =>
        response.Data?.Page; // response.Data.Page will be null when it's requested with CrawlRequestFlag.ThreadClientVersion8888
    protected override RepeatedField<Thread> GetResponsePostList(ThreadResponse response) => response.Data.ThreadList;
    protected override int GetResponseErrorCode(ThreadResponse response) => response.Error.Errorno;
    protected override IEnumerable<Request> GetRequestsForPage(Page page, CancellationToken stoppingToken = default)
    {
        var data602 = GetRequestDataForClientVersion602(page);
        var data = new ThreadRequest.Types.Data
        {
            Kw = forumName,
            Pn = (int)page,
            Rn = 90,
            RnNeed = 30,
            SortType = 5
        };
        return new[]
        {
            new Request(Requester.RequestProtoBuf(EndPointUrl, "12.26.1.0",
                new ThreadRequest {Data = data},
                (req, common) => req.Data.Common = common,
                () => new ThreadResponse(), stoppingToken)),
            new Request(Requester.RequestProtoBuf(LegacyEndPointUrl, "6.0.2",
                new ThreadRequest {Data = data602},
                (req, common) => req.Data.Common = common,
                () => new ThreadResponse(), stoppingToken), CrawlRequestFlag.ThreadClientVersion602)
        };
    }

    protected ThreadRequest.Types.Data GetRequestDataForClientVersion602(Page page) =>
        new()
        {
            Kw = forumName,
            Pn = (int)page,
            Rn = 30
        };
}
