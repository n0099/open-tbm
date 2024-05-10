namespace tbm.Crawler.Tieba.Crawl.Crawler;

public abstract partial class BaseCrawler<TResponse, TPostProtoBuf>
{
    public abstract Exception FillExceptionData(Exception e);
    public abstract IReadOnlyCollection<TPostProtoBuf> GetValidPosts(TResponse response, CrawlRequestFlag flag);
    public abstract TbClient.Page? GetResponsePage(TResponse response);
    protected abstract IReadOnlyCollection<TPostProtoBuf> GetResponsePostList(TResponse response);
    protected abstract int GetResponseErrorCode(TResponse response);
    protected abstract IEnumerable<Request> GetRequestsForPage(Page page, CancellationToken stoppingToken = default);

    public record Response(TResponse Result, CrawlRequestFlag Flag = CrawlRequestFlag.None);
    protected record Request(Task<TResponse> Response, CrawlRequestFlag Flag = CrawlRequestFlag.None);
}
public abstract partial class BaseCrawler<TResponse, TPostProtoBuf>
    : ICrawler<TResponse, TPostProtoBuf>
    where TResponse : class, IMessage<TResponse>
    where TPostProtoBuf : class, IMessage<TPostProtoBuf>
{
    // ReSharper disable once UnusedAutoPropertyAccessor.Global
    public required ClientRequester Requester { protected get; init; }

    public async Task<Response[]> CrawlSinglePage(Page page, CancellationToken stoppingToken = default) =>
        await Task.WhenAll(GetRequestsForPage(page, stoppingToken)
            .Select(async request => new Response(await request.Response, request.Flag)));

    protected void ValidateOtherErrorCode(TResponse response)
    {
        if (GetResponseErrorCode(response) != 0)
            throw new TiebaException("Error from tieba client.") {Data = {{"raw", response}}};
    }

    protected IReadOnlyCollection<TPostProtoBuf> EnsureNonEmptyPostList(TResponse response, string exceptionMessage)
    {
        var posts = GetResponsePostList(response);
        return posts.Count != 0 ? posts : throw new EmptyPostListException(exceptionMessage);
    }
}
