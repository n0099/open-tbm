namespace tbm.Crawler
{
    public sealed class SubReplyCrawler : BaseCrawler<SubReplyResponse, SubReply>
    {
        protected override PropertyInfo ParamDataField => typeof(SubReplyRequest).GetProperty(nameof(SubReplyRequest.Data))!;
        protected override PropertyInfo ParamCommonField => ParamDataField.PropertyType.GetProperty(nameof(SubReplyRequest.Data.Common))!;
        protected override PropertyInfo ResponseDataField => typeof(SubReplyResponse).GetProperty(nameof(SubReplyResponse.Data))!;
        protected override PropertyInfo ResponsePostListField => ResponseDataField.PropertyType.GetProperty(nameof(SubReplyResponse.Data.SubpostList))!;
        protected override PropertyInfo ResponsePageField => ResponseDataField.PropertyType.GetProperty(nameof(SubReplyResponse.Data.Page))!;
        protected override PropertyInfo ResponseErrorField => typeof(SubReplyResponse).GetProperty(nameof(SubReplyResponse.Error))!;

        private readonly Tid _tid;
        private readonly Pid _pid;

        public delegate SubReplyCrawler New(Tid tid, Pid pid);

        public SubReplyCrawler(ClientRequester requester, Tid tid, Pid pid) : base(requester)
        {
            _tid = tid;
            _pid = pid;
        }

        public override Exception FillExceptionData(Exception e)
        {
            e.Data["tid"] = _tid;
            e.Data["pid"] = _pid;
            return e;
        }

        protected override IEnumerable<Request> RequestsFactory(Page page) =>
            new[]
            {
                new Request(Requester.RequestProtoBuf(
                    "c/f/pb/floor?cmd=302002", "12.23.1.0",
                    ParamDataField, ParamCommonField, () => new SubReplyResponse(), new SubReplyRequest
                    {
                        Data = new()
                        {
                            Kz = (long)_tid,
                            Pid = (long)_pid,
                            Pn = (int)page
                        }
                    }), CrawlRequestFlag.None, page)
            };


        public override IList<SubReply> GetValidPosts(SubReplyResponse response)
        {
            switch (response.Error.Errorno)
            {
                case 4:
                    throw new TiebaException(false, "Reply already deleted when crawling sub reply");
                case 28:
                    throw new TiebaException(false, "Thread already deleted when crawling sub reply");
                default:
                    ValidateOtherErrorCode(response);
                    return EnsureNonEmptyPostList(response, "Sub reply list is empty, posts might already deleted from tieba");
            }
        }
    }
}
