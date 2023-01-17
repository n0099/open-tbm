namespace tbm.Crawler.Tieba.Crawl.Crawler
{
    public class ReplyCrawler : BaseCrawler<ReplyResponse, Reply>
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

        protected override RepeatedField<Reply> GetResponsePostList(ReplyResponse response) => response.Data.PostList;
        protected override int GetResponseErrorCode(ReplyResponse response) => response.Error.Errorno;
        public override TbClient.Page GetResponsePage(ReplyResponse response) => response.Data.Page;

        protected override async Task<IEnumerable<Request>> RequestsFactory(Page page)
        {
            const string url = "c/f/pb/page?cmd=302001";
            const string clientVersion = "12.26.1.0";
            var data = new ReplyRequest.Types.Data
            { // reverse order will be {"last", "1"}, {"r", "1"}
                Kz = (long)_tid,
                Pn = (int)page,
                Rn = 30,
                QType = 2
            };
            var response = await Requester.RequestProtoBuf(url, clientVersion,
                new ReplyRequest {Data = data},
                (req, common) => req.Data.Common = common,
                () => new ReplyResponse());
            var ret = new List<Request>(2) {new(Task.FromResult(response))};
            // as of client version 12.12.1.0 (not including), folded replies won't be include in response:
            // https://github.com/n0099/TiebaMonitor/commit/b8e7d2645e456271f52457f56500aaedaf28a010#diff-cf67f7f9e82d44aa5be8f85cd24946e5bb7829ca7940c9d056bb1e3849b8f981R32
            // so we have to manually requesting the folded replies by appending the returned request tasks
            if (response.Data.HasFoldComment != 0)
            {
                var dataShowOnlyFolded = data.Clone();
                dataShowOnlyFolded.IsFoldCommentReq = 1;
                ret.Add(new(Requester.RequestProtoBuf(url, clientVersion,
                    new ReplyRequest {Data = dataShowOnlyFolded},
                    (req, common) => req.Data.Common = common,
                    () => new ReplyResponse()), CrawlRequestFlag.ReplyShowOnlyFolded));
            }
            return ret;
        }

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
}
