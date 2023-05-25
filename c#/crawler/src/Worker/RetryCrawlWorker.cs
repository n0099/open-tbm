using static tbm.Crawler.Worker.MainCrawlWorker;

namespace tbm.Crawler.Worker;

public class RetryCrawlWorker : CyclicCrawlWorker
{
    private readonly ILogger<RetryCrawlWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private readonly IIndex<string, CrawlerLocks> _registeredLocksKeyByType;

    public RetryCrawlWorker(
        ILogger<RetryCrawlWorker> logger,
        IHostApplicationLifetime applicationLifetime,
        IConfiguration config,
        ILifetimeScope scope0,
        IIndex<string, CrawlerLocks> registeredLocksLookup
    ) : base(logger, applicationLifetime, config) =>
        (_logger, _scope0, _registeredLocksKeyByType) = (logger, scope0, registeredLocksLookup);

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        foreach (var lockType in CrawlerLocks.RegisteredCrawlerLocks)
        {
            if (stoppingToken.IsCancellationRequested) return;
            var failed = _registeredLocksKeyByType[lockType].RetryAllFailed();
            if (!failed.Any()) continue; // skip current lock type if there's nothing needs to retry
            if (lockType == "threadLate")
            {
                await using var scope1 = _scope0.BeginLifetimeScope();
                foreach (var tidGroupByFid in failed.Keys.GroupBy(lockId => lockId.Fid, lockId => lockId.Tid))
                {
                    var fid = tidGroupByFid.Key;
                    FailureCount FailureCountSelector(Tid tid) => failed[new(fid, tid)].Single().Value; // it should always contains only one page which is 1
                    var failureCountsKeyByTid = tidGroupByFid.Cast<Tid>().ToDictionary(tid => tid, FailureCountSelector);
                    _logger.LogTrace("Retrying previous failed thread late crawl with fid={}, threadsId={}",
                        fid, Helper.UnescapedJsonSerialize(tidGroupByFid));
                    await scope1.Resolve<ThreadLateCrawlerAndSaver.New>()(fid).Crawl(failureCountsKeyByTid, stoppingToken);
                }
                continue; // skip into next lock type
            }

            await Task.WhenAll(failed.Select(async pair =>
            {
                if (stoppingToken.IsCancellationRequested) return;
                await using var scope1 = _scope0.BeginLifetimeScope();
                var db = scope1.Resolve<CrawlerDbContext.New>()(0);
                var ((fid, tid, pid), failureCountsKeyByPage) = pair;
                var pages = failureCountsKeyByPage.Keys.ToList();
                FailureCount FailureCountSelector(Page p) => failureCountsKeyByPage[p];

                if (lockType == "thread")
                {
                    var forumName = (from f in db.Forum.AsNoTracking()
                        where f.Fid == fid select f.Name).SingleOrDefault();
                    if (forumName == null) return;
                    _logger.LogTrace("Retrying previous failed {} pages in thread crawl for fid={}, forumName={}",
                        failureCountsKeyByPage.Count, fid, forumName);
                    var crawler = scope1.Resolve<ThreadCrawlFacade.New>()(fid, forumName);
                    var savedThreads = await crawler.RetryThenSave(pages, FailureCountSelector, stoppingToken);
                    if (savedThreads == null) return;
                    await CrawlSubReplies(await CrawlReplies(new() {savedThreads}, fid, scope1, stoppingToken), fid, scope1, stoppingToken);
                }
                else if (lockType == "reply" && tid != null)
                {
                    _logger.LogTrace("Retrying previous failed {} pages reply crawl for fid={}, tid={}",
                        failureCountsKeyByPage.Count, fid, tid);
                    var crawler = scope1.Resolve<ReplyCrawlFacade.New>()(fid, tid.Value);
                    var savedReplies = await crawler.RetryThenSave(pages, FailureCountSelector, stoppingToken);
                    if (savedReplies == null) return;
                    var savedRepliesKeyByTid = new Dictionary<PostId, SaverChangeSet<ReplyPost>> {{tid.Value, savedReplies}};
                    await CrawlSubReplies(savedRepliesKeyByTid, fid, scope1, stoppingToken);
                }
                else if (lockType == "subReply" && tid != null && pid != null)
                {
                    _logger.LogTrace("Retrying previous failed {} pages sub reply crawl for fid={}, tid={}, pid={}",
                        failureCountsKeyByPage.Count, fid, tid, pid);
                    var crawler = scope1.Resolve<SubReplyCrawlFacade.New>()(fid, tid.Value, pid.Value);
                    _ = await crawler.RetryThenSave(pages, FailureCountSelector, stoppingToken);
                }
            }));
        }
    }
}
