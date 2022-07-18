namespace tbm.Crawler
{
    public sealed class ThreadCrawler : BaseCrawler<ThreadResponse, Thread>
    {
        protected override PropertyInfo ParamDataField => typeof(ThreadRequest).GetProperty(nameof(ThreadRequest.Data))!;
        protected override PropertyInfo ParamCommonField => ParamDataField.PropertyType.GetProperty(nameof(ThreadRequest.Data.Common))!;
        protected override PropertyInfo ResponseDataField => typeof(ThreadResponse).GetProperty(nameof(ThreadResponse.Data))!;
        protected override PropertyInfo ResponsePostListField => ResponseDataField.PropertyType.GetProperty(nameof(ThreadResponse.Data.ThreadList))!;
        protected override PropertyInfo ResponsePageField => ResponseDataField.PropertyType.GetProperty(nameof(ThreadResponse.Data.Page))!;
        protected override PropertyInfo ResponseErrorField => typeof(ThreadResponse).GetProperty(nameof(ThreadResponse.Error))!;

        private readonly string _forumName;

        public delegate ThreadCrawler New(string forumName);

        public ThreadCrawler(ClientRequester requester, string forumName) : base(requester) => _forumName = forumName;

        public override Exception FillExceptionData(Exception e)
        {
            e.Data["forumName"] = _forumName;
            return e;
        }

        protected override Task<IEnumerable<Request>> RequestsFactory(Page page)
        {
            const string url = "c/f/frs/page?cmd=301001";
            var data602 = new ThreadRequest.Types.Data
            {
                Kw = _forumName,
                Pn = (int)page,
                Rn = 30
            };
            var data = new ThreadRequest.Types.Data
            {
                Kw = _forumName,
                Pn = (int)page,
                Rn = 90,
                RnNeed = 30,
                QType = 2,
                SortType = 5
            };
            return Task.FromResult(new[]
            {
                new Request(Requester.RequestProtoBuf(url, "12.26.1.0", ParamDataField, ParamCommonField, () => new ThreadResponse(),
                    new ThreadRequest {Data = data}), page),
                new Request(Requester.RequestProtoBuf(url, "6.0.2", ParamDataField, ParamCommonField, () => new ThreadResponse(),
                    new ThreadRequest {Data = data602}), page, CrawlRequestFlag.Thread602ClientVersion)
            }.AsEnumerable());
        }

        public override IList<Thread> GetValidPosts(ThreadResponse response, CrawlRequestFlag flag)
        {
            ValidateOtherErrorCode(response);
            return EnsureNonEmptyPostList(response, "Forum threads list is empty, forum might doesn't existed.");
        }
    }
}
