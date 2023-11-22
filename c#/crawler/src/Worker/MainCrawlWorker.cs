using System.Runtime.CompilerServices;

namespace tbm.Crawler.Worker;

public class MainCrawlWorker(
        Func<Owned<CrawlerDbContext.NewDefault>> dbContextDefaultFactory,
        CrawlPost crawlPost)
    : CyclicCrawlWorker
{
    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await foreach (var (fid, forumName) in ForumGenerator(stoppingToken))
            await crawlPost.CrawlSubReplies(await crawlPost.CrawlReplies(await crawlPost.CrawlThreads
                (forumName, fid, stoppingToken), fid, stoppingToken), fid, stoppingToken);
    }

    private async IAsyncEnumerable<FidAndName> ForumGenerator
        ([EnumeratorCancellation] CancellationToken stoppingToken = default)
    {
        await using var dbFactory = dbContextDefaultFactory();
        var forums = (
            from f in dbFactory.Value().Forums.AsNoTracking()
            where f.IsCrawling
            select new FidAndName(f.Fid, f.Name)).ToList();
        var yieldInterval = SyncCrawlIntervalWithConfig() / (float)forums.Count;
        foreach (var fidAndName in forums)
        {
            yield return fidAndName;
            await Task.Delay((yieldInterval * 1000).RoundToUshort(), stoppingToken);
        }
    }

    private sealed record FidAndName(Fid Fid, string Name);
}
