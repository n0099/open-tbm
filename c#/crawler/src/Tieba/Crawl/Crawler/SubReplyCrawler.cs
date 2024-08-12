namespace tbm.Crawler.Tieba.Crawl.Crawler;

public class SubReplyCrawler(Tid tid, Pid pid) : BaseCrawler<SubReplyResponse, SubReply>
{
    public delegate SubReplyCrawler New(Tid tid, Pid pid);

    public override Exception FillExceptionData(Exception e)
    {
        e.Data["tid"] = tid;
        e.Data["pid"] = pid;
        return e;
    }

    public override IReadOnlyCollection<SubReply> GetValidPosts(SubReplyResponse response, CrawlRequestFlag flag)
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

    public override TbClient.Page GetResponsePage(SubReplyResponse response) => response.Data.Page;
    protected override RepeatedField<SubReply> GetResponsePostList(SubReplyResponse response) => response.Data.SubpostList;
    protected override int GetResponseErrorCode(SubReplyResponse response) => response.Error.Errorno;
    protected override IEnumerable<Request> GetRequestsForPage(Page page, CancellationToken stoppingToken = default) =>
    [
        new(Requester.RequestProtoBuf("c/f/pb/floor?cmd=302002", "12.64.1.1",
            new SubReplyRequest {Data = new()
            {
                Kz = (long)tid,
                Pid = (long)pid,
                Pn = (int)page
            }},
            (req, common) => req.Data.Common = common,
            () => new SubReplyResponse(), stoppingToken))
    ];
}
