namespace tbm.Crawler.Tieba.Crawl.Crawler;

public class SubReplyCrawler : BaseCrawler<SubReplyResponse, SubReply>
{
    private readonly Tid _tid;
    private readonly Pid _pid;

    public delegate SubReplyCrawler New(Tid tid, Pid pid);

    public SubReplyCrawler(ClientRequester requester, Tid tid, Pid pid) :
        base(requester) => (_tid, _pid) = (tid, pid);

    public override Exception FillExceptionData(Exception e)
    {
        e.Data["tid"] = _tid;
        e.Data["pid"] = _pid;
        return e;
    }

    protected override RepeatedField<SubReply> GetResponsePostList(SubReplyResponse response) => response.Data.SubpostList;
    protected override int GetResponseErrorCode(SubReplyResponse response) => response.Error.Errorno;
    public override TbClient.Page GetResponsePage(SubReplyResponse response) => response.Data.Page;

    protected override IEnumerable<Request> GetRequestsForPage(Page page, CancellationToken stoppingToken = default) => new[]
    {
        new Request(Requester.RequestProtoBuf("c/f/pb/floor?cmd=302002", "12.26.1.0",
            new SubReplyRequest {Data = new()
            {
                Kz = (long)_tid,
                Pid = (long)_pid,
                Pn = (int)page
            }},
            (req, common) => req.Data.Common = common,
            () => new SubReplyResponse(), stoppingToken))
    };

    public override IList<SubReply> GetValidPosts(SubReplyResponse response, CrawlRequestFlag flag)
    {
        switch (response.Error.Errorno)
        {
            case 4:
                throw new TiebaException(shouldRetry: false,
                    "Reply already deleted when crawling sub reply.");
            case 28:
                throw new TiebaException(shouldRetry: false,
                    "Thread already deleted when crawling sub reply.");
            default:
                ValidateOtherErrorCode(response);
                return EnsureNonEmptyPostList(response,
                    "Sub reply list is empty, posts might already deleted from tieba.");
        }
    }
}
