namespace tbm.Crawler
{
    using SavedRepliesByTid = ConcurrentDictionary<Tid, SaverChangeSet<ReplyPost>>;

    public class ArchiveCrawlWorker : BackgroundService
    {
        // as of March 2019, tieba had restrict the max accepted value for page param of forum's threads api
        // any request with page offset that larger than 10k threads will be response with results from the first page
        private const int MaxCrawlablePage = 334; // 10k threads / 30 per request (from Rn param) = 333.3...
        private readonly ILogger<ArchiveCrawlWorker> _logger;
        private readonly ILifetimeScope _scope0;
        private readonly string _forumName = "";
        private readonly Fid _fid = 0;
        public ArchiveCrawlWorker(ILogger<ArchiveCrawlWorker> logger, ILifetimeScope scope0)
        {
            _logger = logger;
            _scope0 = scope0;
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            try
            {
                var pages = Enumerable.Range(1, Math.Min(MaxCrawlablePage, await GetTotalPageForForum()));
                await Parallel.ForEachAsync(pages, stoppingToken, async (page, _) =>
                {
                    var stopWatch = new Stopwatch();
                    stopWatch.Start();
                    await CrawlSubReplies(await CrawlReplies(await CrawlThreads((Page)page, _forumName, _fid), _fid), _fid);
                    _logger.LogInformation("Archive for forum {}, page {} finished after {:F2}s", _forumName, page, stopWatch.ElapsedMilliseconds / 1000f);
                });
                _logger.LogInformation("Archive for forum {}, all pages 1~{} finished.", _forumName, MaxCrawlablePage);
            }
            catch (Exception e)
            {
                _logger.LogError(e, "Exception");
            }
        }

        private async Task<int> GetTotalPageForForum()
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            return (await scope1.Resolve<ThreadArchiveCrawler.New>()(_forumName).CrawlSinglePage(1))
                .Select(response => response.Result.Data.Page.TotalPage).Max();
        }

        private async Task<SaverChangeSet<ThreadPost>?> CrawlThreads(Page page, string forumName, Fid fid)
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var crawler = scope1.Resolve<ThreadArchiveCrawlFacade.New>()(fid, forumName);
            var savedThreads = (await crawler.CrawlPageRange(page, page)).SaveAll();
            if (savedThreads != null)
            {
                await scope1.Resolve<ThreadLateCrawlerAndSaver.New>()(fid)
                    .Crawl(savedThreads.NewlyAdded.ToDictionary(t => t.Tid, _ => (FailedCount)0));
            }
            return savedThreads;
        }

        private async Task<SavedRepliesByTid> CrawlReplies(SaverChangeSet<ThreadPost>? savedThreads, Fid fid)
        {
            var shouldCrawlReplyTid = new HashSet<Tid>();
            var savedRepliesByTid = new SavedRepliesByTid();
            if (savedThreads == null) return savedRepliesByTid;
            savedThreads.AllAfter.ForEach(t => shouldCrawlReplyTid.Add(t.Tid));

            await Task.WhenAll(shouldCrawlReplyTid.Select(async tid =>
            {
                await using var scope1 = _scope0.BeginLifetimeScope();
                var crawler = scope1.Resolve<ReplyCrawlFacade.New>()(fid, tid);
                savedRepliesByTid.SetIfNotNull(tid, (await crawler.CrawlPageRange(1)).SaveAll());
            }));
            return savedRepliesByTid;
        }

        private async Task CrawlSubReplies(SavedRepliesByTid savedRepliesByTid, Fid fid)
        {
            var shouldCrawlSubReplyPid = savedRepliesByTid.Aggregate(new HashSet<(Tid, Pid)>(), (shouldCrawl, tidAndReplies) =>
            {
                var (tid, replies) = tidAndReplies;
                replies.AllAfter.ForEach(r => shouldCrawl.Add((tid, r.Pid)));
                return shouldCrawl;
            });
            await Task.WhenAll(shouldCrawlSubReplyPid.Select(async tidAndPid =>
            {
                var (tid, pid) = tidAndPid;
                await using var scope1 = _scope0.BeginLifetimeScope();
                _ = (await scope1.Resolve<SubReplyCrawlFacade.New>()(fid, tid, pid).CrawlPageRange(1)).SaveAll();
            }));
        }
    }
}
