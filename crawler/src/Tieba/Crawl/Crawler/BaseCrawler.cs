namespace tbm.Crawler
{
    public abstract class BaseCrawler<TResponse, TPostProtoBuf>
        where TResponse : IMessage<TResponse> where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        protected record Request(Task<TResponse> Response, Page Page, CrawlRequestFlag Flag = CrawlRequestFlag.None);
        protected ClientRequester Requester { get; }
        protected abstract PropertyInfo ParamDataField { get; }
        protected abstract PropertyInfo ParamCommonField { get; }
        protected abstract PropertyInfo ResponseDataField { get; }
        protected abstract PropertyInfo ResponsePostListField { get; }
        protected abstract PropertyInfo ResponsePageField { get; }
        protected abstract PropertyInfo ResponseErrorField { get; }

        public abstract Exception FillExceptionData(Exception e);
        protected abstract Task<IEnumerable<Request>> RequestsFactory(Page page);
        public abstract IList<TPostProtoBuf> GetValidPosts(TResponse response);

        protected BaseCrawler(ClientRequester requester) => Requester = requester;

        public TbClient.Page GetPageFromResponse(TResponse res) =>
            (TbClient.Page)ResponsePageField.GetValue(ResponseDataField.GetValue(res) as IMessage)!;

        public async Task<(TResponse, CrawlRequestFlag, Page)[]> CrawlSinglePage(Page page) =>
            await Task.WhenAll((await RequestsFactory(page)).Select(async i => (await i.Response, i.Flag, i.Page)));

        protected void ValidateOtherErrorCode(TResponse response)
        {
            if ((ResponseErrorField.GetValue(response) as Error)?.Errorno != 0)
                throw new TiebaException("Error from tieba client.") {Data = {{"raw", response}}};
        }

        protected IList<TPostProtoBuf> EnsureNonEmptyPostList(TResponse response, string exceptionMessage) =>
            ResponsePostListField.GetValue(ResponseDataField.GetValue(response)) is IList<TPostProtoBuf> posts
            && posts.Any() ? posts : throw new TiebaException(false, exceptionMessage);
    }
}
