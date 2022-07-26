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
                foreach (var pages in Enumerable.Range(1, Math.Min(MaxCrawlablePage, await GetTotalPageForForum())).Chunk(Environment.ProcessorCount))
                {
                    await Parallel.ForEachAsync(pages, stoppingToken, async (page, _) =>
                    {
                        var stopWatchTotal = new Stopwatch();
                        stopWatchTotal.Start();
                        var stopWatch = new Stopwatch();
                        stopWatch.Start();
                        float GetElapsedMsThenRestart()
                        {
                            var ret = stopWatch.ElapsedMilliseconds / 1000f;
                            stopWatch.Restart();
                            return ret;
                        }
                        var savedThreads = await CrawlThreads((Page)page, _forumName, _fid);
                        if (savedThreads == null) return;
                        var savedThreadsCount = savedThreads.AllAfter.Count;
                        _logger.LogInformation("Archive for {} threads in the page {} of forum {} finished after {:F2}s",
                            savedThreadsCount, page, _forumName, GetElapsedMsThenRestart());

                        var savedReplies = await CrawlReplies(savedThreads, _fid);
                        var savedRepliesCount = savedReplies.Select(i => i.Value.AllAfter.Count).Sum();
                        _logger.LogInformation("Archive for {} replies within {} threads in the page {} of forum {} finished after {:F2}s",
                            savedRepliesCount, savedThreadsCount, page, _forumName, GetElapsedMsThenRestart());

                        var savedSubRepliesCount = await CrawlSubReplies(savedReplies, _fid);
                        _logger.LogInformation("Archive for {} sub replies within {} replies within {} threads in the page {} of forum {} finished after {:F2}s",
                            savedSubRepliesCount, savedRepliesCount, savedThreadsCount, page, _forumName, GetElapsedMsThenRestart());
                        _logger.LogInformation("Archive for a total of {} posts in the page {} of forum {} finished after {:F2}s",
                            savedSubRepliesCount + savedRepliesCount + savedThreadsCount, page, _forumName, stopWatchTotal.ElapsedMilliseconds / 1000f);
                    });
                }
                _logger.LogInformation("Archive for all pages 1~{} of forum {} finished.", _forumName, MaxCrawlablePage);
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

        private async Task<int> CrawlSubReplies(SavedRepliesByTid savedRepliesByTid, Fid fid)
        {
            var shouldCrawlSubReplyPid = savedRepliesByTid.Aggregate(new HashSet<(Tid, Pid)>(), (shouldCrawl, tidAndReplies) =>
            {
                var (tid, replies) = tidAndReplies;
                replies.AllAfter.ForEach(r => shouldCrawl.Add((tid, r.Pid)));
                return shouldCrawl;
            });
            var savedSubRepliesCount = 0;
            await Task.WhenAll(shouldCrawlSubReplyPid.Select(async tidAndPid =>
            {
                var (tid, pid) = tidAndPid;
                await using var scope1 = _scope0.BeginLifetimeScope();
                var saved = (await scope1.Resolve<SubReplyCrawlFacade.New>()(fid, tid, pid).CrawlPageRange(1)).SaveAll();
                if (saved == null) return;
                _ = Interlocked.Add(ref savedSubRepliesCount, saved.AllAfter.Count);
            }));
            return savedSubRepliesCount;
        }
    }
}
