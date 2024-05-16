namespace tbm.Crawler.Worker;

public class RetryCrawlWorker(
    ILogger<RetryCrawlWorker> logger,
    IIndex<CrawlerLocks.Type, CrawlerLocks> registeredLocksLookup,
    CrawlPost crawlPost,
    Func<Owned<CrawlerDbContext.NewDefault>> dbContextDefaultFactory,
    Func<Owned<ThreadLateCrawlFacade.New>> threadLateCrawlFacadeFactory,
    Func<Owned<ThreadCrawlFacade.New>> threadCrawlFacadeFactory,
    Func<Owned<ReplyCrawlFacade.New>> replyCrawlFacadeFactory,
    Func<Owned<SubReplyCrawlFacade.New>> subReplyCrawlFacadeFactory)
    : CyclicCrawlWorker
{
    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        foreach (var lockType in Enum.GetValues<CrawlerLocks.Type>())
        {
            if (stoppingToken.IsCancellationRequested) return;
            var failed = registeredLocksLookup[lockType].RetryAllFailed();
            if (failed.Count == 0) continue; // skip current lock type if there's nothing needs to retry
            if (lockType == CrawlerLocks.Type.ThreadLate)
            {
                await RetryThreadLate(failed, stoppingToken);

                // skip into next lock type since unable to distinguish between lockId of threadLate and thread
                continue;
            }
            await Task.WhenAll(failed.Select(RetryFailed(lockType, stoppingToken)));
        }
    }

    private Func<KeyValuePair<CrawlerLocks.LockId, IReadOnlyDictionary<Page, FailureCount>>, Task> RetryFailed
        (CrawlerLocks.Type lockType, CancellationToken stoppingToken = default) => async failedPagesKeyByLockId =>
    {
        if (stoppingToken.IsCancellationRequested) return;
        var ((fid, tid, pid), failureCountsKeyByPage) = failedPagesKeyByLockId;
        var pages = failureCountsKeyByPage.Keys.ToList();
        FailureCount FailureCountSelector(Page p) => failureCountsKeyByPage[p];

        switch (lockType)
        {
            case CrawlerLocks.Type.Thread:
                await RetryThread(fid, pages,
                    failureCountsKeyByPage.Count, FailureCountSelector, stoppingToken);
                break;
            case CrawlerLocks.Type.Reply when tid != null:
                await RetryReply(fid, tid.Value, pages,
                    failureCountsKeyByPage.Count, FailureCountSelector, stoppingToken);
                break;
            case CrawlerLocks.Type.SubReply when tid != null && pid != null:
                await RetrySubReply(fid, tid.Value, pid.Value, pages,
                    failureCountsKeyByPage.Count, FailureCountSelector, stoppingToken);
                break;
        }
    };

    private async Task RetryThreadLate(
        IReadOnlyDictionary<CrawlerLocks.LockId, IReadOnlyDictionary<Page, FailureCount>> failureCountWithPagesKeyByLockId,
        CancellationToken stoppingToken = default)
    {
        await using var threadLateFacade = threadLateCrawlFacadeFactory();
        foreach (var tidGroupByFid in failureCountWithPagesKeyByLockId
                     .Keys.GroupBy(lockId => lockId.Fid, lockId => lockId.Tid))
        {
            var fid = tidGroupByFid.Key;
            FailureCount FailureCountSelector(Tid tid) =>

                // it should always contain only one page which is 1
                failureCountWithPagesKeyByLockId[new(fid, tid)].Single().Value;
            var failureCountsKeyByTid = tidGroupByFid
                .Cast<Tid>().ToDictionary(tid => tid, FailureCountSelector);
            logger.LogTrace("Retrying previous failed thread late crawl with fid={}, threadsId={}",
                fid, SharedHelper.UnescapedJsonSerialize(tidGroupByFid));
            await threadLateFacade.Value(fid).CrawlThenSave(failureCountsKeyByTid, stoppingToken);
        }
    }

    private async Task RetryThread(
        Fid fid,
        IReadOnlyList<Page> pages,
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
        await using var facadeFactory = threadCrawlFacadeFactory();
        var facade = facadeFactory.Value(fid, forumName);
        var savedThreads = await facade.RetryThenSave(pages, failureCountSelector, stoppingToken);
        if (savedThreads == null) return;
        var savedReplies = await crawlPost.CrawlReplies
            ([savedThreads], fid, stoppingToken);
        await crawlPost.CrawlSubReplies(savedReplies, fid, stoppingToken);
    }

    private async Task RetryReply(
        Fid fid, Tid tid,
        IReadOnlyList<Page> pages,
        int failureCount,
        Func<Page, FailureCount> failureCountSelector,
        CancellationToken stoppingToken = default)
    {
        logger.LogTrace("Retrying previous failed {} pages reply crawl for fid={}, tid={}",
            failureCount, fid, tid);
        await using var facadeFactory = replyCrawlFacadeFactory();
        var facade = facadeFactory.Value(fid, tid);
        var savedReplies = await facade.RetryThenSave(pages, failureCountSelector, stoppingToken);
        if (savedReplies == null) return;
        var savedRepliesKeyByTid = new Dictionary<PostId, SaverChangeSet<ReplyPost>> {{tid, savedReplies}};
        await crawlPost.CrawlSubReplies(savedRepliesKeyByTid, fid, stoppingToken);
    }

    private async Task RetrySubReply(
        Fid fid, Tid tid, Pid pid,
        IReadOnlyList<Page> pages,
        int failureCount,
        Func<Page, FailureCount> failureCountSelector,
        CancellationToken stoppingToken = default)
    {
        logger.LogTrace("Retrying previous failed {} pages sub reply crawl for fid={}, tid={}, pid={}",
            failureCount, fid, tid, pid);
        await using var facadeFactory = subReplyCrawlFacadeFactory();
        var facade = facadeFactory.Value(fid, tid, pid);
        _ = await facade.RetryThenSave(pages, failureCountSelector, stoppingToken);
    }
}
