using static tbm.Crawler.MainCrawlWorker;

namespace tbm.Crawler
{
    public class RetryCrawlWorker : CyclicCrawlWorker
    {
        private readonly ILogger<RetryCrawlWorker> _logger;
        private readonly ILifetimeScope _scope0;
        private readonly IIndex<string, CrawlerLocks.New> _registeredLocksFactory;

        public RetryCrawlWorker(ILogger<RetryCrawlWorker> logger, IConfiguration config,
            ILifetimeScope scope0, IIndex<string, CrawlerLocks.New> registeredLocksFactory) : base(config)
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
                    var failed = _registeredLocksFactory[lockType](lockType).RetryAllFailed();
                    if (!failed.Any()) continue; // skip current lock type if there's nothing needs to retry
                    if (lockType == "threadLate")
                    {
                        await using var scope1 = _scope0.BeginLifetimeScope();
                        var db = scope1.Resolve<TbmDbContext.New>()(0);
                        var tidAndFidRecords = from t in db.PostsIndex where t.Type == "thread" && failed.Keys.Contains(t.Tid) select new {t.Fid, t.Tid};
                        foreach (var tidGroupByFid in tidAndFidRecords.ToList().GroupBy(i => i.Fid))
                        {
                            FailedCount FailedCountSelector(Tid tid) => failed[tid].First().Value; // it should always contains only one page which is 1
                            var tidAndFailedCountList = tidGroupByFid.ToDictionary(g => g.Tid, g => FailedCountSelector(g.Tid));
                            _logger.LogTrace("Retrying previous failed thread late crawl with fid:{}, threadsId:{}",
                                tidGroupByFid.Key, Helper.UnescapedJsonSerialize(tidAndFailedCountList.Select(i => i.Key)));
                            await scope1.Resolve<ThreadLateCrawlerAndSaver.New>()(tidGroupByFid.Key).Crawl(tidAndFailedCountList);
                        }
                        continue; // skip into next lock type
                    }

                    await Task.WhenAll(failed.Select(async indexPagesPair =>
                    {
                        await using var scope1 = _scope0.BeginLifetimeScope();
                        var db = scope1.Resolve<TbmDbContext.New>()(0);
                        var (fidOrPostId, failsCountByPage) = indexPagesPair;
                        var pages = failsCountByPage.Keys;
                        FailedCount FailedCountSelector(Page p) => failsCountByPage[p];
                        if (lockType == "thread")
                        {
                            var forumName = (from f in db.ForumsInfo where f.Fid == fidOrPostId select f.Name).FirstOrDefault();
                            if (forumName == null) return;
                            var fid = (Fid)fidOrPostId;
                            _logger.LogTrace("Retrying previous failed {} pages in thread crawl for fid:{}, forumName:{}", failsCountByPage.Count, fid, forumName);
                            var crawler = scope1.Resolve<ThreadCrawlFacade.New>()(fid, forumName);
                            var savedThreads = await crawler.RetryThenSave(pages, FailedCountSelector);
                            if (savedThreads == null) return;
                            await CrawlSubReplies(await CrawlReplies(new() {savedThreads}, fid, scope1), fid, scope1);
                        }
                        else if (lockType == "reply")
                        {
                            var parentsId = (from p in db.PostsIndex where p.Type == "thread" && p.Tid == fidOrPostId select new {p.Fid, p.Tid}).FirstOrDefault();
                            if (parentsId == null) return;
                            _logger.LogTrace("Retrying previous failed {} pages reply crawl for fid:{}, tid:{}", failsCountByPage.Count, parentsId.Fid, parentsId.Tid);
                            var crawler = scope1.Resolve<ReplyCrawlFacade.New>()(parentsId.Fid, parentsId.Tid);
                            var savedReplies = await crawler.RetryThenSave(pages, FailedCountSelector);
                            if (savedReplies == null) return;
                            await CrawlSubReplies(new Dictionary<ulong, SaverChangeSet<ReplyPost>> {{parentsId.Tid, savedReplies}}, parentsId.Fid, scope1);
                        }
                        else if (lockType == "subReply")
                        {
                            var parentsId = (from p in db.PostsIndex where p.Type == "reply" && p.Pid == fidOrPostId select new {p.Fid, p.Tid, p.Pid}).FirstOrDefault();
                            if (parentsId == null) return;
                            _logger.LogTrace("Retrying previous failed {} pages sub reply crawl for fid:{}, tid:{}, pid:{}", failsCountByPage.Count, parentsId.Fid, parentsId.Tid, parentsId.Pid);
                            var crawler = scope1.Resolve<SubReplyCrawlFacade.New>()(parentsId.Fid, parentsId.Tid, parentsId.Pid ?? 0);
                            _ = await crawler.RetryThenSave(pages, FailedCountSelector);
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
