namespace tbm.Crawler
{
    using SavedThreads = List<SaverChangeSet<ThreadPost>>;
    using SavedRepliesByTid = ConcurrentDictionary<Tid, SaverChangeSet<ReplyPost>>;

    public class MainCrawlWorker : BackgroundService
    {
        private readonly ILogger<MainCrawlWorker> _logger;
        private readonly IConfiguration _config;
        private readonly ILifetimeScope _scope0;
        // stores the latestReplyTime of first thread appears in the page of previous crawl worker, key by fid
        private readonly Dictionary<Fid, Time> _latestReplyTimeCheckpointCache = new();
        private readonly Timer _timer = new() {Enabled = true};

        public MainCrawlWorker(ILogger<MainCrawlWorker> logger, IConfiguration config, ILifetimeScope scope0)
        {
            _logger = logger;
            _config = config;
            _scope0 = scope0;
            _ = SyncCrawlIntervalWithConfig();
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            _timer.Elapsed += async (_, _) => await CrawlThenSave();
            await CrawlThenSave();
        }

        private int SyncCrawlIntervalWithConfig()
        {
            var interval = _config.GetValue("CrawlInterval", 60);
            _timer.Interval = interval * 1000;
            return interval;
        }

        private record FidAndName(Fid Fid, string Name);

        private async IAsyncEnumerable<FidAndName> ForumGenerator()
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<TbmDbContext.New>()(0);
            var forums = (from f in db.ForumsInfo where f.IsCrawling select new FidAndName(f.Fid, f.Name)).ToList();
            var yieldInterval = SyncCrawlIntervalWithConfig() / forums.Count;
            foreach (var fidAndName in forums)
            {
                await Task.Delay(yieldInterval * 1000);
                yield return fidAndName;
            }
        }

        private async Task CrawlThenSave()
        {
            try
            {
                await foreach (var (fid, forumName) in ForumGenerator())
                    await CrawlSubReplies(await CrawlReplies(await CrawlThreads(forumName, fid), fid), fid);
            }
            catch (Exception e)
            {
                _logger.LogError(e, "Exception");
            }
        }

        private async Task<SavedThreads> CrawlThreads(string forumName, Fid fid)
        {
            var savedThreads = new SavedThreads();
            Time lastThreadTime;
            Page crawlingPage = 0;
            await using var scope1 = _scope0.BeginLifetimeScope();
            if (!_latestReplyTimeCheckpointCache.TryGetValue(fid, out var timeInPreviousCrawl))
                // get the largest value of field latestReplyTime in all stored threads of this forum
                // this approach is not as accurate as extracting the last thread in the response list and needs a full table scan on db
                // https://stackoverflow.com/questions/341264/max-or-default
                timeInPreviousCrawl = scope1.Resolve<TbmDbContext.New>()(fid).Threads.Max(t => (Time?)t.LatestReplyTime) ?? Time.MaxValue;
            do
            {
                crawlingPage++;
                await using var scope2 = scope1.BeginLifetimeScope();
                var crawler = scope2.Resolve<ThreadCrawlFacade.New>()(fid, forumName);
                savedThreads.AddIfNotNull((await crawler.CrawlPageRange(crawlingPage, crawlingPage)).SavePosts());
                if (crawler.FirstAndLastPostInPages.TryGetValue(crawlingPage, out var threadsTuple))
                {
                    var (firstThread, lastThread) = threadsTuple;
                    lastThreadTime = lastThread.LatestReplyTime;
                    if (crawlingPage == 1) _latestReplyTimeCheckpointCache[fid] = firstThread.LatestReplyTime;
                }
                else
                { // retry this page
                    crawlingPage--;
                    lastThreadTime = Time.MaxValue;
                }
            } while (lastThreadTime > timeInPreviousCrawl);

            await Task.WhenAll(savedThreads.Select(async threads =>
            {
                await using var scope3 = _scope0.BeginLifetimeScope();
                await scope3.Resolve<ThreadLateCrawlerAndSaver.New>()(fid)
                    .Crawl(threads.NewlyAdded.Select(t => new ThreadLateCrawlerAndSaver.TidAndFailedCount(t.Tid, 0)));
            }));

            return savedThreads;
        }

        private async Task<SavedRepliesByTid> CrawlReplies(SavedThreads savedThreads, Fid fid)
        {
            var shouldCrawlReplyTid = savedThreads.Aggregate(new HashSet<Tid>(), (shouldCrawl, threads) =>
            {
                threads.NewlyAdded.ForEach(i => shouldCrawl.Add(i.Tid));
                threads.Existing.ForEach(beforeAndAfter =>
                {
                    var (before, after) = beforeAndAfter;
                    if (before.ReplyNum != after.ReplyNum
                        || before.LatestReplyTime != after.LatestReplyTime
                        || before.LatestReplierUid != after.LatestReplierUid)
                        _ = shouldCrawl.Add(before.Tid);
                });
                return shouldCrawl;
            });
            var savedRepliesByTid = new SavedRepliesByTid();
            await Task.WhenAll(shouldCrawlReplyTid.Select(async tid =>
            {
                await using var scope1 = _scope0.BeginLifetimeScope();
                var crawler = scope1.Resolve<ReplyCrawlFacade.New>()(fid, tid);
                savedRepliesByTid.SetIfNotNull(tid, (await crawler.CrawlPageRange(1)).SavePosts());
            }));
            return savedRepliesByTid;
        }

        private async Task CrawlSubReplies(SavedRepliesByTid savedRepliesByTid, Fid fid)
        {
            var shouldCrawlSubReplyPid = savedRepliesByTid.Aggregate(new HashSet<(Tid, Pid)>(), (shouldCrawl, tidAndReplies) =>
            {
                var (tid, replies) = tidAndReplies;
                replies.NewlyAdded.ForEach(i =>
                {
                    if (i.SubReplyNum != 0) _ = shouldCrawl.Add((tid, i.Pid));
                });
                replies.Existing.ForEach(beforeAndAfter =>
                {
                    var (before, after) = beforeAndAfter;
                    if (after.SubReplyNum != 0 && before.SubReplyNum != after.SubReplyNum) _ = shouldCrawl.Add((tid, before.Pid));
                });
                return shouldCrawl;
            });
            await Task.WhenAll(shouldCrawlSubReplyPid.Select(async tidAndPid =>
            {
                var (tid, pid) = tidAndPid;
                await using var scope1 = _scope0.BeginLifetimeScope();
                _ = (await scope1.Resolve<SubReplyCrawlFacade.New>()(fid, tid, pid).CrawlPageRange(1)).SavePosts();
            }));
        }
    }
}
