namespace tbm.Crawler
{
    public class RetryCrawlWorker : BackgroundService
    {
        private readonly ILogger<Worker> _logger;
        private readonly IIndex<string, CrawlerLocks.New> _registeredLocksFactory;
        private readonly Timer _timer = new() {Interval = Interval};
        private const int Interval = 60 * 1000; // per minute

        public RetryCrawlWorker(ILogger<Worker> logger, IIndex<string, CrawlerLocks.New> registeredLocksFactory)
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
            foreach (var lockType in Program.RegisteredCrawlerLocks)
            {
                var crawlerLock = _registeredLocksFactory[lockType](lockType);
                foreach (var indexPagesPair in crawlerLock.RetryAllFailed())
                {
                    await using var scope = Program.Autofac.BeginLifetimeScope();
                    var db = scope.Resolve<TbmDbContext.New>()(0);
                    if (lockType == "thread")
                    {
                        var fid = (Fid)indexPagesPair.Key;
                        var forumName = (from f in db.ForumsInfo where f.Fid == fid select f.Name).FirstOrDefault();
                        if (forumName == null) return;
                        var crawler = scope.Resolve<ThreadCrawlFacade.New>()(fid, forumName);
                        await crawler.CrawlPages(indexPagesPair.Value.Keys);
                        crawler.SavePosts<ThreadRevision>(out _, out _, out _);
                    }

                    if (lockType == "threadLate")
                    {
                        var tids = from t in db.PostsIndex where t.Type == "thread" && indexPagesPair.Value.Keys.Any(i => i == t.Tid) select new {t.Fid, t.Tid};
                        foreach (var g in tids.ToList().GroupBy(pair => pair.Fid))
                        {
                            await scope.Resolve<ThreadLateCrawlerAndSaver.New>()(g.Key, g.Select(i => i.Tid)).Crawl();
                        }
                    }

                    if (lockType == "reply")
                    {
                        var parentsId = (from p in db.PostsIndex where p.Type == "reply" && p.Pid == indexPagesPair.Key select new {p.Fid, p.Tid}).FirstOrDefault();
                        if (parentsId == null) return;
                        var crawler = scope.Resolve<ReplyCrawlFacade.New>()(parentsId.Fid, parentsId.Tid);
                        await crawler.CrawlPages(indexPagesPair.Value.Keys);
                        crawler.SavePosts<ReplyRevision>(out _, out _, out _);
                    }

                    if (lockType == "subReply")
                    {
                        var parentsId = (from p in db.PostsIndex where p.Type == "subReply" && p.Spid == indexPagesPair.Key select new {p.Fid, p.Tid, p.Pid}).FirstOrDefault();
                        if (parentsId == null) return;
                        var crawler = scope.Resolve<SubReplyCrawlFacade.New>()(parentsId.Fid, parentsId.Tid, parentsId.Pid);
                        await crawler.CrawlPages(indexPagesPair.Value.Keys);
                        crawler.SavePosts<SubReplyRevision>(out _, out _, out _);
                    }
                }
            }
        }
    }
}
