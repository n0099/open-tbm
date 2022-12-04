namespace tbm.Crawler.Tieba.Crawl.Crawler
{
    public abstract class BaseCrawler<TResponse, TPostProtoBuf>
        where TResponse : IMessage<TResponse> where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        public record Response(TResponse Result, CrawlRequestFlag Flag = CrawlRequestFlag.None);
        protected record Request(Task<TResponse> Response, Page Page, CrawlRequestFlag Flag = CrawlRequestFlag.None);
        protected ClientRequester Requester { get; }
        protected abstract PropertyInfo ParamDataProp { get; }
        protected abstract PropertyInfo ParamCommonProp { get; }
        protected abstract PropertyInfo ResponseDataProp { get; }
        protected abstract PropertyInfo ResponsePostListProp { get; }
        protected abstract PropertyInfo ResponsePageProp { get; }
        protected abstract PropertyInfo ResponseErrorProp { get; }

        public abstract Exception FillExceptionData(Exception e);
        protected abstract Task<IEnumerable<Request>> RequestsFactory(Page page);
        public abstract IList<TPostProtoBuf> GetValidPosts(TResponse response, CrawlRequestFlag flag);

        protected BaseCrawler(ClientRequester requester) => Requester = requester;

        public TbClient.Page? GetPageFromResponse(TResponse res) =>
            (TbClient.Page?)ResponsePageProp.GetValue(ResponseDataProp.GetValue(res) as IMessage);

        public async Task<Response[]> CrawlSinglePage(Page page) =>
            await Task.WhenAll((await RequestsFactory(page)).Select(async i => new Response(await i.Response, i.Flag)));

        protected void ValidateOtherErrorCode(TResponse response)
        {
            if ((ResponseErrorProp.GetValue(response) as Error)?.Errorno != 0)
                throw new TiebaException("Error from tieba client.") {Data = {{"raw", response}}};
        }

        protected IList<TPostProtoBuf> EnsureNonEmptyPostList(TResponse response, string exceptionMessage) =>
            ResponsePostListProp.GetValue(ResponseDataProp.GetValue(response)) is IList<TPostProtoBuf> posts
            && posts.Any() ? posts : throw new TiebaException(false, exceptionMessage);
    }
}
