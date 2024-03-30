namespace tbm.Crawler.Tieba.Crawl.Facade;

public class ThreadLateCrawlFacade(
    CrawlerDbContext.New dbContextFactory,
    ThreadLateCrawler.New crawlerFactory,
    Fid fid)
{
    public delegate ThreadLateCrawlFacade New(Fid fid);

    public async Task CrawlThenSave(
        IDictionary<Tid, FailureCount> failureCountsKeyByTid,
        CancellationToken stoppingToken = default)
    {
        var threads = await Task.WhenAll(
            failureCountsKeyByTid.Select(pair => crawlerFactory(fid).Crawl(pair.Key, pair.Value, stoppingToken)));

        var db = dbContextFactory(fid);
        await using var transaction = await db.Database.BeginTransactionAsync(stoppingToken);

        db.AttachRange(threads.OfType<ThreadPost>()); // remove nulls due to exception
        db.ChangeTracker.Entries<ThreadPost>()
            .ForEach(ee => ee.Property(th => th.AuthorPhoneType).IsModified = true);

        // do not touch UpdateAt field for the accuracy of time field in thread revisions
        _ = await db.SaveChangesAsync(stoppingToken);
        await transaction.CommitAsync(stoppingToken);
    }
}
