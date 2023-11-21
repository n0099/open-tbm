using static tbm.Crawler.Worker.MainCrawlWorker;

namespace tbm.Crawler.Worker;

public class RetryCrawlWorker(
        ILogger<RetryCrawlWorker> logger,
        ILifetimeScope scope0,
        IIndex<string, CrawlerLocks> registeredLocksLookup)
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
                await using var scope1 = scope0.BeginLifetimeScope();
                foreach (var tidGroupByFid in failed.Keys.GroupBy(lockId => lockId.Fid, lockId => lockId.Tid))
                {
                    var fid = tidGroupByFid.Key;
                    FailureCount FailureCountSelector(Tid tid) => failed[new(fid, tid)].Single().Value; // it should always contains only one page which is 1
                    var failureCountsKeyByTid = tidGroupByFid.Cast<Tid>().ToDictionary(tid => tid, FailureCountSelector);
                    logger.LogTrace("Retrying previous failed thread late crawl with fid={}, threadsId={}",
                        fid, Helper.UnescapedJsonSerialize(tidGroupByFid));
                    await scope1.Resolve<ThreadLateCrawlerAndSaver.New>()(fid).CrawlThenSave(failureCountsKeyByTid, stoppingToken);
                }
                continue; // skip into next lock type
            }

            await Task.WhenAll(failed.Select(async pair =>
            {
                if (stoppingToken.IsCancellationRequested) return;
                await using var scope1 = scope0.BeginLifetimeScope();
                var db = scope1.Resolve<CrawlerDbContext.NewDefault>()();
                var ((fid, tid, pid), failureCountsKeyByPage) = pair;
                var pages = failureCountsKeyByPage.Keys.ToList();
                FailureCount FailureCountSelector(Page p) => failureCountsKeyByPage[p];

                if (lockType == "thread")
                {
                    var forumName = (
                        from f in db.Forums.AsNoTracking()
                        where f.Fid == fid
                        select f.Name).SingleOrDefault();
                    if (forumName == null) return;
                    logger.LogTrace("Retrying previous failed {} pages in thread crawl for fid={}, forumName={}",
                        failureCountsKeyByPage.Count, fid, forumName);
                    var crawler = scope1.Resolve<ThreadCrawlFacade.New>()(fid, forumName);
                    var savedThreads = await crawler.RetryThenSave(pages, FailureCountSelector, stoppingToken);
                    if (savedThreads == null) return;
                    await CrawlSubReplies(await CrawlReplies(new() {savedThreads}, fid, scope1, stoppingToken), fid, scope1, stoppingToken);
                }
                else if (lockType == "reply" && tid != null)
                {
                    logger.LogTrace("Retrying previous failed {} pages reply crawl for fid={}, tid={}",
                        failureCountsKeyByPage.Count, fid, tid);
                    var crawler = scope1.Resolve<ReplyCrawlFacade.New>()(fid, tid.Value);
                    var savedReplies = await crawler.RetryThenSave(pages, FailureCountSelector, stoppingToken);
                    if (savedReplies == null) return;
                    var savedRepliesKeyByTid = new Dictionary<PostId, SaverChangeSet<ReplyPost>> {{tid.Value, savedReplies}};
                    await CrawlSubReplies(savedRepliesKeyByTid, fid, scope1, stoppingToken);
                }
                else if (lockType == "subReply" && tid != null && pid != null)
                {
                    logger.LogTrace("Retrying previous failed {} pages sub reply crawl for fid={}, tid={}, pid={}",
                        failureCountsKeyByPage.Count, fid, tid, pid);
                    var crawler = scope1.Resolve<SubReplyCrawlFacade.New>()(fid, tid.Value, pid.Value);
                    _ = await crawler.RetryThenSave(pages, FailureCountSelector, stoppingToken);
                }
            }));
        }
    }
}
