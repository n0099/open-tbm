namespace tbm.Crawler
{
    public class RetryCrawlWorker : BackgroundService
    {
        private readonly ILogger<RetryCrawlWorker> _logger;
        private readonly IIndex<string, CrawlerLocks.New> _registeredLocksFactory;
        private readonly Timer _timer = new() {Interval = Interval};
        private const int Interval = 60 * 1000; // per minute

        public RetryCrawlWorker(ILogger<RetryCrawlWorker> logger, IIndex<string, CrawlerLocks.New> registeredLocksFactory)
        {
            _logger = logger;
            _registeredLocksFactory = registeredLocksFactory;
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            await Task.Delay(Interval / 2, stoppingToken);
            _timer.Enabled = true; // delay timer start to stagger execution with main crawling worker
            _timer.Elapsed += async (_, _) => await Retry();
            await Retry();
        }

        private async Task Retry()
        {
            try
            {
                foreach (var lockType in Program.RegisteredCrawlerLocks)
                {
                    var failed = _registeredLocksFactory[lockType](lockType).RetryAllFailed();
                    if (!failed.Any()) continue; // skip current lock type if there's nothing needs to retry
                    if (lockType == "threadLate")
                    {
                        await using var scope = Program.Autofac.BeginLifetimeScope();
                        var db = scope.Resolve<TbmDbContext.New>()(0);
                        var tidAndFidRecords = from t in db.PostsIndex where t.Type == "thread" && failed.Keys.Contains(t.Tid) select new {t.Fid, t.Tid};
                        foreach (var tidGroupByFid in tidAndFidRecords.ToList().GroupBy(record => record.Fid))
                        {
                            FailedCount FailedCountSelector(Tid tid) => failed.First(i => i.Key == tid).Value.First().FailedCount; // it should always contains only one page which is 1
                            var threadsId = tidGroupByFid.Select(g => new ThreadLateCrawlerAndSaver.TidAndFailedCount(g.Tid, FailedCountSelector(g.Tid))).ToList();
                            _logger.LogTrace("Retrying previous failed thread late crawl with fid:{}, threadsId:{}", tidGroupByFid.Key, Helper.UnescapedJsonSerialize(threadsId.Select(i => i.Tid)));
                            await scope.Resolve<ThreadLateCrawlerAndSaver.New>()(tidGroupByFid.Key).Crawl(threadsId);
                        }

                        continue; // skip into next lock type
                    }

                    await Task.WhenAll(failed.Select(async indexPagesPair =>
                    {
                        await using var scope = Program.Autofac.BeginLifetimeScope();
                        var db = scope.Resolve<TbmDbContext.New>()(0);
                        var (fidOrPostId, pageAndFailedCountRecords) = indexPagesPair;
                        if (lockType == "thread")
                        {
                            var forumName = (from f in db.ForumsInfo where f.Fid == fidOrPostId select f.Name).FirstOrDefault();
                            if (forumName == null) return;
                            _logger.LogTrace("Retrying previous failed {} pages in thread crawl for fid:{}, forumName:{}", pageAndFailedCountRecords.Count, fidOrPostId, forumName);
                            var crawler = scope.Resolve<ThreadCrawlFacade.New>()((Fid)fidOrPostId, forumName);
                            await crawler.RetryPages(pageAndFailedCountRecords);
                            _ = crawler.SavePosts();
                        }
                        else if (lockType == "reply")
                        {
                            var parentsId = (from p in db.PostsIndex where p.Type == "thread" && p.Tid == fidOrPostId select new {p.Fid, p.Tid}).FirstOrDefault();
                            if (parentsId == null) return;
                            _logger.LogTrace("Retrying previous failed {} pages reply crawl for fid:{}, tid:{}", pageAndFailedCountRecords.Count, parentsId.Fid, parentsId.Tid);
                            var crawler = scope.Resolve<ReplyCrawlFacade.New>()(parentsId.Fid, parentsId.Tid);
                            await crawler.RetryPages(pageAndFailedCountRecords);
                            _ = crawler.SavePosts();
                        }
                        else if (lockType == "subReply")
                        {
                            var parentsId = (from p in db.PostsIndex where p.Type == "reply" && p.Pid == fidOrPostId select new {p.Fid, p.Tid, p.Pid}).FirstOrDefault();
                            if (parentsId == null) return;
                            _logger.LogTrace("Retrying previous failed {} pages sub reply crawl for fid:{}, tid:{}, pid:{}", pageAndFailedCountRecords.Count, parentsId.Fid, parentsId.Tid, parentsId.Pid);
                            var crawler = scope.Resolve<SubReplyCrawlFacade.New>()(parentsId.Fid, parentsId.Tid, parentsId.Pid ?? 0);
                            await crawler.RetryPages(pageAndFailedCountRecords);
                            _ = crawler.SavePosts();
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
