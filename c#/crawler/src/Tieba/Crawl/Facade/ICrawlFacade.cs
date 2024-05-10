namespace tbm.Crawler.Tieba.Crawl.Facade;

public interface ICrawlFacade<TPost> : IDisposable
    where TPost : BasePost
{
    public delegate void ExceptionHandler(Exception ex);

    public ICrawlFacade<TPost> AddExceptionHandler(ExceptionHandler handler);

    public SaverChangeSet<TPost>? SaveCrawled(CancellationToken stoppingToken = default);

    public Task<ICrawlFacade<TPost>> CrawlPageRange(
        Page startPage,
        Page endPage = Page.MaxValue,
        CancellationToken stoppingToken = default);

    public Task<SaverChangeSet<TPost>?> RetryThenSave(
        IReadOnlyList<Page> pages,
        Func<Page, FailureCount> failureCountSelector,
        CancellationToken stoppingToken = default);
}
