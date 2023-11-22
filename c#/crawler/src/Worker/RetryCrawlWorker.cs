using static tbm.Crawler.Worker.MainCrawlWorker;

namespace tbm.Crawler.Worker;

public class RetryCrawlWorker(
        ILogger<RetryCrawlWorker> logger,
        IIndex<string, CrawlerLocks> registeredLocksLookup,
        Func<Owned<CrawlerDbContext.New>> dbContextFactory,
        Func<Owned<CrawlerDbContext.NewDefault>> dbContextDefaultFactory,
        Func<Owned<ThreadLateCrawlerAndSaver.New>> threadLateCrawlerAndSaverFactory,
        Func<Owned<ThreadCrawlFacade.New>> threadCrawlFacadeFactory,
        Func<Owned<ReplyCrawlFacade.New>> replyCrawlFacadeFactory,
        Func<Owned<SubReplyCrawlFacade.New>> subReplyCrawlFacadeFactory)
    : CyclicCrawlWorker
{
    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        foreach (var lockType in CrawlerLocks.RegisteredCrawlerLocks)
        {
            if (stoppingToken.IsCancellationRequested) return;
            var failed = registeredLocksLookup[lockType].RetryAllFailed();
            if (!failed.Any()) continue; // skip current lock type if there's nothing needs to retry
            if (lockType == "threadLate")
            {
                await using var threadLate = threadLateCrawlerAndSaverFactory();
                foreach (var tidGroupByFid in failed.Keys.GroupBy(lockId => lockId.Fid, lockId => lockId.Tid))
                {
                    var fid = tidGroupByFid.Key;
                    FailureCount FailureCountSelector(Tid tid) => failed[new(fid, tid)].Single().Value; // it should always contains only one page which is 1
                    var failureCountsKeyByTid = tidGroupByFid.Cast<Tid>().ToDictionary(tid => tid, FailureCountSelector);
                    logger.LogTrace("Retrying previous failed thread late crawl with fid={}, threadsId={}",
                        fid, Helper.UnescapedJsonSerialize(tidGroupByFid));
                    await threadLate.Value(fid).CrawlThenSave(failureCountsKeyByTid, stoppingToken);
                }
                continue; // skip into next lock type
            }

            await Task.WhenAll(failed.Select(async pair =>
            {
                if (stoppingToken.IsCancellationRequested) return;
                var ((fid, tid, pid), failureCountsKeyByPage) = pair;
                var pages = failureCountsKeyByPage.Keys.ToList();
                FailureCount FailureCountSelector(Page p) => failureCountsKeyByPage[p];

                if (lockType == "thread")
                {
                    string? GetForumName()
                    {
                        using var dbFactory = dbContextDefaultFactory();
                        return (
                            from f in dbFactory.Value().Forums.AsNoTracking()
                            where f.Fid == fid
                            select f.Name).SingleOrDefault();
                    }
                    var forumName = GetForumName();
                    if (forumName == null) return;

                    logger.LogTrace("Retrying previous failed {} pages in thread crawl for fid={}, forumName={}",
                        failureCountsKeyByPage.Count, fid, forumName);
                    await using var crawlerFactory = threadCrawlFacadeFactory();
                    var crawler = crawlerFactory.Value(fid, forumName);
                    var savedThreads = await crawler.RetryThenSave(pages, FailureCountSelector, stoppingToken);
                    if (savedThreads == null) return;
                    await CrawlSubReplies(subReplyCrawlFacadeFactory,
                        await CrawlReplies(dbContextFactory, replyCrawlFacadeFactory, new() {savedThreads},
                            fid, stoppingToken),
                        fid, stoppingToken);
                }
                else if (lockType == "reply" && tid != null)
                {
                    logger.LogTrace("Retrying previous failed {} pages reply crawl for fid={}, tid={}",
                        failureCountsKeyByPage.Count, fid, tid);
                    await using var crawlerFactory = replyCrawlFacadeFactory();
                    var crawler = crawlerFactory.Value(fid, tid.Value);
                    var savedReplies = await crawler.RetryThenSave(pages, FailureCountSelector, stoppingToken);
                    if (savedReplies == null) return;
                    var savedRepliesKeyByTid = new Dictionary<PostId, SaverChangeSet<ReplyPost>> {{tid.Value, savedReplies}};
                    await CrawlSubReplies(subReplyCrawlFacadeFactory, savedRepliesKeyByTid, fid, stoppingToken);
                }
                else if (lockType == "subReply" && tid != null && pid != null)
                {
                    logger.LogTrace("Retrying previous failed {} pages sub reply crawl for fid={}, tid={}, pid={}",
                        failureCountsKeyByPage.Count, fid, tid, pid);
                    await using var crawlerFactory = subReplyCrawlFacadeFactory();
                    var crawler = crawlerFactory.Value(fid, tid.Value, pid.Value);
                    _ = await crawler.RetryThenSave(pages, FailureCountSelector, stoppingToken);
                }
            }));
        }
    }
}
