namespace tbm.Crawler.Tieba.Crawl.Crawler;

public class ThreadCrawler : BaseCrawler<ThreadResponse, Thread>
{
    private readonly string _forumName;

    public delegate ThreadCrawler New(string forumName);

    public ThreadCrawler(ClientRequester requester, string forumName) : base(requester) => _forumName = forumName;

    public override Exception FillExceptionData(Exception e)
    {
        e.Data["forumName"] = _forumName;
        return e;
    }

    protected override RepeatedField<Thread> GetResponsePostList(ThreadResponse response) => response.Data.ThreadList;
    protected override int GetResponseErrorCode(ThreadResponse response) => response.Error.Errorno;
    public override TbClient.Page? GetResponsePage(ThreadResponse response) =>
        response.Data?.Page; // response.Data.Page will be null when it's requested with CrawlRequestFlag.ThreadClientVersion8888

    protected const string EndPointUrl = "c/f/frs/page?cmd=301001";

    protected ThreadRequest.Types.Data GetRequestDataForClientVersion602(Page page) =>
        new()
        {
            Kw = _forumName,
            Pn = (int)page,
            Rn = 30
        };

    protected override Task<IEnumerable<Request>> GetRequestsForPage(Page page)
    {
        var data602 = GetRequestDataForClientVersion602(page);
        var data = new ThreadRequest.Types.Data
        {
            Kw = _forumName,
            Pn = (int)page,
            Rn = 90,
            RnNeed = 30,
            SortType = 5
        };
        return Task.FromResult(new[]
        {
            new Request(Requester.RequestProtoBuf(EndPointUrl, "12.26.1.0",
                new ThreadRequest {Data = data},
                (req, common) => req.Data.Common = common,
                () => new ThreadResponse())),
            new Request(Requester.RequestProtoBuf(EndPointUrl, "6.0.2",
                new ThreadRequest {Data = data602},
                (req, common) => req.Data.Common = common,
                () => new ThreadResponse()), CrawlRequestFlag.ThreadClientVersion602)
        }.AsEnumerable());
    }

    public override IList<Thread> GetValidPosts(ThreadResponse response, CrawlRequestFlag flag)
    {
        ValidateOtherErrorCode(response);
        return EnsureNonEmptyPostList(response, "Forum threads list is empty, forum might doesn't existed.");
    }
}
