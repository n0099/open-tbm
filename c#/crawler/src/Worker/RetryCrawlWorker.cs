namespace tbm.Crawler.Worker;

public class RetryCrawlWorker(
        ILogger<RetryCrawlWorker> logger,
        IIndex<string, CrawlerLocks> registeredLocksLookup,
        CrawlPost crawlPost,
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
                await RetryThreadLate(failed, stoppingToken);
                continue; // skip into next lock type since unable to distinguish between lockId of threadLate and thread
            }
            await Task.WhenAll(failed.Select(RetryFailed(lockType, stoppingToken)));
        }
    }

    private Func<KeyValuePair<CrawlerLocks.LockId, Dictionary<Page, FailureCount>>, Task> RetryFailed
        (string lockType, CancellationToken stoppingToken = default) => async failedPagesKeyByLockId =>
    {
        if (stoppingToken.IsCancellationRequested) return;
        var ((fid, tid, pid), failureCountsKeyByPage) = failedPagesKeyByLockId;
        var pages = failureCountsKeyByPage.Keys.ToList();
        FailureCount FailureCountSelector(Page p) => failureCountsKeyByPage[p];

        if (lockType == "thread")
        {
            await RetryThread(fid, pages, failureCountsKeyByPage.Count, FailureCountSelector, stoppingToken);
        }
        else if (lockType == "reply" && tid != null)
        {
            await RetryReply(fid, tid.Value, pages, failureCountsKeyByPage.Count, FailureCountSelector, stoppingToken);
        }
        else if (lockType == "subReply" && tid != null && pid != null)
        {
            await RetrySubReply(fid, tid.Value, pid.Value, pages, failureCountsKeyByPage.Count, FailureCountSelector, stoppingToken);
        }
    };

    private async Task RetryThreadLate(
        Dictionary<CrawlerLocks.LockId, Dictionary<Page, FailureCount>> failureCountWithPagesKeyByLockId,
        CancellationToken stoppingToken = default)
    {
        await using var threadLate = threadLateCrawlerAndSaverFactory();
        foreach (var tidGroupByFid in failureCountWithPagesKeyByLockId
                     .Keys.GroupBy(lockId => lockId.Fid, lockId => lockId.Tid))
        {
            var fid = tidGroupByFid.Key;
            FailureCount FailureCountSelector(Tid tid) =>
                failureCountWithPagesKeyByLockId[new(fid, tid)].Single().Value; // it should always contains only one page which is 1
            var failureCountsKeyByTid = tidGroupByFid
                .Cast<Tid>().ToDictionary(tid => tid, FailureCountSelector);
            logger.LogTrace("Retrying previous failed thread late crawl with fid={}, threadsId={}",
                fid, Helper.UnescapedJsonSerialize(tidGroupByFid));
            await threadLate.Value(fid).CrawlThenSave(failureCountsKeyByTid, stoppingToken);
        }
    }

    private async Task RetryThread(
        Fid fid,
        IList<Page> pages,
        int failureCount,
        Func<Page, FailureCount> failureCountSelector,
        CancellationToken stoppingToken = default)
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
            failureCount, fid, forumName);
        await using var crawlerFactory = threadCrawlFacadeFactory();
        var crawler = crawlerFactory.Value(fid, forumName);
        var savedThreads = await crawler.RetryThenSave(pages, failureCountSelector, stoppingToken);
        if (savedThreads == null) return;
        var savedReplies = await crawlPost.CrawlReplies
            (new() {savedThreads}, fid, stoppingToken);
        await crawlPost.CrawlSubReplies(savedReplies, fid, stoppingToken);
    }

    private async Task RetryReply(
        Fid fid, Tid tid,
        IList<Page> pages,
        int failureCount,
        Func<Page, FailureCount> failureCountSelector,
        CancellationToken stoppingToken = default)
    {
        logger.LogTrace("Retrying previous failed {} pages reply crawl for fid={}, tid={}",
            failureCount, fid, tid);
        await using var crawlerFactory = replyCrawlFacadeFactory();
        var crawler = crawlerFactory.Value(fid, tid);
        var savedReplies = await crawler.RetryThenSave(pages, failureCountSelector, stoppingToken);
        if (savedReplies == null) return;
        var savedRepliesKeyByTid = new Dictionary<PostId, SaverChangeSet<ReplyPost>> {{tid, savedReplies}};
        await crawlPost.CrawlSubReplies(savedRepliesKeyByTid, fid, stoppingToken);
    }

    private async Task RetrySubReply(
        Fid fid, Tid tid, Pid pid,
        IList<Page> pages,
        int failureCount,
        Func<Page, FailureCount> failureCountSelector,
        CancellationToken stoppingToken = default)
    {
        logger.LogTrace("Retrying previous failed {} pages sub reply crawl for fid={}, tid={}, pid={}",
            failureCount, fid, tid, pid);
        await using var crawlerFactory = subReplyCrawlFacadeFactory();
        var crawler = crawlerFactory.Value(fid, tid, pid);
        _ = await crawler.RetryThenSave(pages, failureCountSelector, stoppingToken);
    }
}
