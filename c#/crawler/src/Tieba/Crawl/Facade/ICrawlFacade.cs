namespace tbm.Crawler.Tieba.Crawl.Facade;

public interface ICrawlFacade<TPost, TParsedPost> : IDisposable
    where TPost : BasePost
    where TParsedPost : TPost, BasePost.IParsed
{
    public delegate void ExceptionHandler(Exception ex);

    public ICrawlFacade<TPost, TParsedPost> AddExceptionHandler(ExceptionHandler handler);

    public SaverChangeSet<TPost, TParsedPost>? SaveCrawled(CancellationToken stoppingToken = default);

    public Task<ICrawlFacade<TPost, TParsedPost>> CrawlPageRange(
        Page startPage,
        Page endPage = Page.MaxValue,
        CancellationToken stoppingToken = default);

    public Task<SaverChangeSet<TPost, TParsedPost>?> RetryThenSave(
        IReadOnlyList<Page> pages,
        Func<Page, FailureCount> failureCountSelector,
        CancellationToken stoppingToken = default);
}
