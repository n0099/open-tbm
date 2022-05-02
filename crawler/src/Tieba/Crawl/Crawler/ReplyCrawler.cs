namespace tbm.Crawler
{
    public sealed class ReplyCrawler : BaseCrawler<ReplyResponse, Reply>
    {
        private readonly Fid _fid;
        private readonly Tid _tid;

        public delegate ReplyCrawler New(Fid fid, Tid tid);

        public ReplyCrawler(ClientRequester requester, Fid fid, Tid tid) : base(requester)
        {
            _fid = fid;
            _tid = tid;
        }

        public override Exception FillExceptionData(Exception e)
        {
            e.Data["tid"] = _tid;
            return e;
        }

        protected override IEnumerable<(Task<ReplyResponse>, CrawlRequestFlag, Page)> RequestsFactory(Page page) =>
            new[]
            {
                (Requester.RequestProtoBuf<ReplyRequest, ReplyResponse>(
                    "http://c.tieba.baidu.com/c/f/pb/page?cmd=302001",
                    new ReplyRequest
                    {
                        Data = new ReplyRequest.Types.Data
                        { // reverse order will be {"last", "1"}, {"r", "1"}
                            Kz = (long)_tid,
                            Pn = (int)page,
                            Rn = 30,
                            QType = 2
                        }
                    },
                    "12.12.1.0"
                ), CrawlRequestFlag.None, page)
            };

        public override IList<Reply> GetValidPosts(ReplyResponse response)
        {
            if (response.Error.Errorno == 4)
                throw new TiebaException("Thread already deleted when crawling reply");
            if (response.Data.Forum.Id != _fid)
                throw new TiebaException(false, "Parent forum id within thread response is not match with the param value of crawler ctor, this thread might be multi forum or livepost");
            ValidateOtherErrorCode(response);
            return EnsureNonEmptyPostList(response, 6,
                "Reply list is empty, posts might already deleted from tieba");
        }
    }
}
