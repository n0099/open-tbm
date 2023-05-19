namespace tbm.Crawler.Tieba.Crawl.Crawler;

public class ReplyCrawler : BaseCrawler<ReplyResponse, Reply>
{
    private readonly Fid _fid;
    private readonly Tid _tid;

    public delegate ReplyCrawler New(Fid fid, Tid tid);

    public ReplyCrawler(ClientRequester requester, Fid fid, Tid tid) : base(requester) => (_fid, _tid) = (fid, tid);

    public override Exception FillExceptionData(Exception e)
    {
        e.Data["tid"] = _tid;
        return e;
    }

    protected override RepeatedField<Reply> GetResponsePostList(ReplyResponse response) => response.Data.PostList;
    protected override int GetResponseErrorCode(ReplyResponse response) => response.Error.Errorno;
    public override TbClient.Page GetResponsePage(ReplyResponse response) => response.Data.Page;

    protected override IEnumerable<Request> GetRequestsForPage(Page page, CancellationToken stoppingToken = default) => new[]
    {
        new Request(Requester.RequestProtoBuf("c/f/pb/page?cmd=302001", "12.26.1.0",
            new ReplyRequest {Data = new()
            { // reverse order will be {"last", "1"}, {"r", "1"}
                Kz = (long)_tid,
                Pn = (int)page,
                Rn = 30
            }},
            (req, common) => req.Data.Common = common,
            () => new ReplyResponse(), stoppingToken))
    };

    public override IList<Reply> GetValidPosts(ReplyResponse response, CrawlRequestFlag flag)
    {
        switch (response.Error.Errorno)
        {
            case 4 when flag == CrawlRequestFlag.ReplyShowOnlyFolded:
                // silent exception and do not retry when error_no=4 and the response is request with is_fold_comment_req=1
                throw new TiebaException(false, true);
            case 4 or 350008:
                throw new EmptyPostListException("Thread already deleted when crawling reply.");
        }
        ValidateOtherErrorCode(response);

        var ret = EnsureNonEmptyPostList(response, "Reply list is empty, posts might already deleted from tieba.");
        var fid = response.Data.Forum.Id;
        if (fid != _fid) // fid will be the protoBuf default value 0 when reply list is empty, so we EnsureNonEmptyPostList() by first
            throw new TiebaException(false,
                $"Parent forum id within thread response: {fid} is not match with the param value of crawler ctor: {_fid}"
                + ", this thread might be multi forum or \"livepost\" thread.");
        return ret;
    }
}
