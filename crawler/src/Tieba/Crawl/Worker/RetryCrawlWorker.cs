using static tbm.Crawler.MainCrawlWorker;

namespace tbm.Crawler
{
    public class RetryCrawlWorker : CyclicCrawlWorker
    {
        private readonly ILogger<RetryCrawlWorker> _logger;
        private readonly ILifetimeScope _scope0;
        private readonly IIndex<string, CrawlerLocks> _registeredLocksFactory;

        public RetryCrawlWorker(ILogger<RetryCrawlWorker> logger, IConfiguration config,
            ILifetimeScope scope0, IIndex<string, CrawlerLocks> registeredLocksFactory) : base(config)
        {
            _logger = logger;
            _scope0 = scope0;
            _registeredLocksFactory = registeredLocksFactory;
            _ = SyncCrawlIntervalWithConfig();
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            Timer.Elapsed += async (_, _) => await Retry();
            await Retry();
        }

        private async Task Retry()
        {
            _ = SyncCrawlIntervalWithConfig();
            try
            {
                foreach (var lockType in Program.RegisteredCrawlerLocks)
                {
                    var failed = _registeredLocksFactory[lockType].RetryAllFailed();
                    if (!failed.Any()) continue; // skip current lock type if there's nothing needs to retry
                    if (lockType == "threadLate")
                    {
                        await using var scope1 = _scope0.BeginLifetimeScope();
                        var db = scope1.Resolve<TbmDbContext.New>()(0);
                        foreach (var tidGroupByFid in failed.Keys.GroupBy(i => i.Fid, i => i.Tid))
                        {
                            var fid = tidGroupByFid.Key;
                            FailureCount FailureCountSelector(Tid tid) => failed[new (fid, tid)].First().Value; // it should always contains only one page which is 1
                            var failureCountsKeyByTid = tidGroupByFid.Cast<Tid>().ToDictionary(tid => tid, FailureCountSelector);
                            _logger.LogTrace("Retrying previous failed thread late crawl with fid={}, threadsId={}",
                                fid, Helper.UnescapedJsonSerialize(tidGroupByFid));
                            await scope1.Resolve<ThreadLateCrawlerAndSaver.New>()(fid).Crawl(failureCountsKeyByTid);
                        }
                        continue; // skip into next lock type
                    }

                    await Task.WhenAll(failed.Select(async pair =>
                    {
                        await using var scope1 = _scope0.BeginLifetimeScope();
                        var db = scope1.Resolve<TbmDbContext.New>()(0);
                        var (lockId, failureCountsKeyByPage) = pair;
                        var pages = failureCountsKeyByPage.Keys;
                        FailureCount FailureCountSelector(Page p) => failureCountsKeyByPage[p];

                        if (lockType == "thread")
                        {
                            var fid = lockId.Fid;
                            var forumName = (from f in db.ForumsInfo where f.Fid == fid select f.Name).FirstOrDefault();
                            if (forumName == null) return;
                            _logger.LogTrace("Retrying previous failed {} pages in thread crawl for fid={}, forumName={}",
                                failureCountsKeyByPage.Count, fid, forumName);
                            var crawler = scope1.Resolve<ThreadCrawlFacade.New>()(fid, forumName);
                            var savedThreads = await crawler.RetryThenSave(pages, FailureCountSelector);
                            if (savedThreads == null) return;
                            await CrawlSubReplies(await CrawlReplies(new() {savedThreads}, fid, scope1), fid, scope1);
                        }
                        else if (lockType == "reply" && lockId.Tid != null)
                        {
                            _logger.LogTrace("Retrying previous failed {} pages reply crawl for fid={}, tid={}",
                                failureCountsKeyByPage.Count, lockId.Fid, lockId.Tid);
                            var crawler = scope1.Resolve<ReplyCrawlFacade.New>()(lockId.Fid, lockId.Tid.Value);
                            var savedReplies = await crawler.RetryThenSave(pages, FailureCountSelector);
                            if (savedReplies == null) return;
                            await CrawlSubReplies(new Dictionary<PostId, SaverChangeSet<ReplyPost>> {{lockId.Tid.Value, savedReplies}}, lockId.Fid, scope1);
                        }
                        else if (lockType == "subReply" && lockId.Tid != null && lockId.Pid != null)
                        {
                            _logger.LogTrace("Retrying previous failed {} pages sub reply crawl for fid={}, tid={}, pid={}",
                                failureCountsKeyByPage.Count, lockId.Fid, lockId.Tid, lockId.Pid);
                            var crawler = scope1.Resolve<SubReplyCrawlFacade.New>()(lockId.Fid, lockId.Tid.Value, lockId.Pid.Value);
                            _ = await crawler.RetryThenSave(pages, FailureCountSelector);
                        }
                    }));
                }
            }
            catch (Exception e)
            {
                _logger.LogError(e, "Exception");
            }
        }
    }
}
