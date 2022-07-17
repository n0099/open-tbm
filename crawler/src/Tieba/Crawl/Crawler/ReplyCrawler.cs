namespace tbm.Crawler
{
    public sealed class ReplyCrawler : BaseCrawler<ReplyResponse, Reply>
    {
        protected override PropertyInfo ParamDataField => typeof(ReplyRequest).GetProperty(nameof(ReplyRequest.Data))!;
        protected override PropertyInfo ParamCommonField => ParamDataField.PropertyType.GetProperty(nameof(ReplyRequest.Data.Common))!;
        protected override PropertyInfo ResponseDataField => typeof(ReplyResponse).GetProperty(nameof(ReplyResponse.Data))!;
        protected override PropertyInfo ResponsePostListField => ResponseDataField.PropertyType.GetProperty(nameof(ReplyResponse.Data.PostList))!;
        protected override PropertyInfo ResponsePageField => ResponseDataField.PropertyType.GetProperty(nameof(ReplyResponse.Data.Page))!;
        protected override PropertyInfo ResponseErrorField => typeof(ReplyResponse).GetProperty(nameof(ReplyResponse.Error))!;

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

        protected override IEnumerable<Request> RequestsFactory(Page page)
        {
            const string url = "c/f/pb/page?cmd=302001";
            var data = new ReplyRequest.Types.Data
            { // reverse order will be {"last", "1"}, {"r", "1"}
                Kz = (long)_tid,
                Pn = (int)page,
                Rn = 30,
                QType = 2
            };
            var dataShowOnlyFolded = data.Clone();
            dataShowOnlyFolded.IsFoldCommentReq = 1;
            return new[]
            {
                new Request(Requester.RequestProtoBuf(url, "12.26.1.0", ParamDataField, ParamCommonField,
                    () => new ReplyResponse(), new ReplyRequest {Data = data}), CrawlRequestFlag.None, page),
                new Request(Requester.RequestProtoBuf(url, "12.26.1.0", ParamDataField, ParamCommonField,
                    () => new ReplyResponse(), new ReplyRequest {Data = dataShowOnlyFolded}), CrawlRequestFlag.None, page)
            };
        }

        public override IList<Reply> GetValidPosts(ReplyResponse response)
        {
            if (response.Error.Errorno is 4 or 350008)
                throw new TiebaException(false, "Thread already deleted when crawling reply.");
            ValidateOtherErrorCode(response);
            var ret = EnsureNonEmptyPostList(response, "Reply list is empty, posts might already deleted from tieba.");
            if (response.Data.Forum.Id != _fid) // response.Data.Forum.Id will be the default value 0 when reply list is empty
                throw new TiebaException(false, $"Parent forum id within thread response: {response.Data.Forum.Id} is not match with the param value of crawler ctor: {_fid}, this thread might be multi forum or livepost.");
            return ret;
        }
    }
}
