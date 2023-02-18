namespace tbm.Crawler.Tieba.Crawl.Crawler;

public abstract class BaseCrawler<TResponse, TPostProtoBuf>
    where TResponse : IMessage<TResponse>
    where TPostProtoBuf : IMessage<TPostProtoBuf>
{
    public record Response(TResponse Result, CrawlRequestFlag Flag = CrawlRequestFlag.None);
    protected record Request(Task<TResponse> Response, CrawlRequestFlag Flag = CrawlRequestFlag.None);

    protected ClientRequester Requester { get; }

    protected BaseCrawler(ClientRequester requester) => Requester = requester;

    public abstract Exception FillExceptionData(Exception e);
    protected abstract RepeatedField<TPostProtoBuf> GetResponsePostList(TResponse response);
    protected abstract int GetResponseErrorCode(TResponse response);
    public abstract TbClient.Page? GetResponsePage(TResponse response);
    protected abstract Task<IEnumerable<Request>> RequestsFactory(Page page);
    public abstract IList<TPostProtoBuf> GetValidPosts(TResponse response, CrawlRequestFlag flag);

    public async Task<Response[]> CrawlSinglePage(Page page, CancellationToken stoppingToken = default) =>
        await Task.WhenAll((await RequestsFactoryWithCancellationToken(page, stoppingToken))
            .Select(async request => new Response(await request.Response, request.Flag)));

    private Task<IEnumerable<Request>> RequestsFactoryWithCancellationToken(Page page, CancellationToken stoppingToken)
    {
        stoppingToken.ThrowIfCancellationRequested();
        return RequestsFactory(page);
    }

    protected void ValidateOtherErrorCode(TResponse response)
    {
        if (GetResponseErrorCode(response) != 0)
            throw new TiebaException("Error from tieba client.") {Data = {{"raw", response}}};
    }

    protected IList<TPostProtoBuf> EnsureNonEmptyPostList(TResponse response, string exceptionMessage)
    {
        var posts = GetResponsePostList(response);
        return posts.Any() ? posts : throw new EmptyPostListException(exceptionMessage);
    }
}
