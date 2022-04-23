namespace tbm.Crawler
{
    public abstract class BaseCrawler<TResponse, TPostProtoBuf>
        where TResponse : IMessage<TResponse> where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        protected ClientRequester Requester { get; }

        public abstract Exception FillExceptionData(Exception e);
        protected abstract IEnumerable<(Task<TResponse>, CrawlRequestFlag, Page)> RequestsFactory(Page page);
        public abstract IList<TPostProtoBuf> GetValidPosts(TResponse response);

        protected BaseCrawler(ClientRequester requester) => Requester = requester;

        public Task<(TResponse, CrawlRequestFlag, Page)[]> CrawlSinglePage(Page page) =>
            Task.WhenAll(RequestsFactory(page).Select(async i => (await i.Item1, i.Item2, i.Item3)));

        protected static void ValidateOtherErrorCode(TResponse response)
        {
            var error = (Error)response.Descriptor.FindFieldByName("error").Accessor.GetValue(response);
            if (error.Errorno != 0)
                throw new TiebaException($"Error from tieba client, raw json: {response}");
        }

        protected static IList<TPostProtoBuf> EnsureNonEmptyPostList(TResponse response, int fieldNum, string exceptionMessage)
        {
            var data = (IMessage)response.Descriptor.FindFieldByName("data").Accessor.GetValue(response);
            var posts = (IList<TPostProtoBuf>)data.Descriptor.FindFieldByNumber(fieldNum).Accessor.GetValue(data);
            return posts.Any() ? posts : throw new TiebaException(exceptionMessage);
        }
    }
}
