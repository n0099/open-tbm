namespace tbm.Crawler
{
    using SavedThreads = List<SaverChangeSet<ThreadPost>>;
    using SavedRepliesByTid = ConcurrentDictionary<Tid, SaverChangeSet<ReplyPost>>;

    public class MainCrawlWorker : BackgroundService
    {
        private readonly ILogger<MainCrawlWorker> _logger;
        private readonly TbmDbContext.New _dbContextFactory;
        private readonly ThreadCrawlFacade.New _threadCrawlFacadeFactory;
        private readonly ReplyCrawlFacade.New _replyCrawlFacadeFactory;
        private readonly SubReplyCrawlFacade.New _subReplyCrawlFacadeFactory;
        private readonly ThreadLateCrawlerAndSaver.New _threadLateCrawlerAndSaverFactory;
        // stores the latestReplyTime of first thread appears in the page of previous crawl worker, key by fid
        private readonly Dictionary<Fid, Time> _latestReplyTimeCheckpointCache = new();
        private readonly Timer _timer = new() {Enabled = true, Interval = 60 * 1000}; // per minute

        public MainCrawlWorker(ILogger<MainCrawlWorker> logger,
            TbmDbContext.New dbContextFactory,
            ThreadCrawlFacade.New threadCrawlFacadeFactory,
            ReplyCrawlFacade.New replyCrawlFacadeFactory,
            SubReplyCrawlFacade.New subReplyCrawlFacadeFactory,
            ThreadLateCrawlerAndSaver.New threadLateCrawlerAndSaverFactory)
        {
            _logger = logger;
            _dbContextFactory = dbContextFactory;
            _threadCrawlFacadeFactory = threadCrawlFacadeFactory;
            _replyCrawlFacadeFactory = replyCrawlFacadeFactory;
            _subReplyCrawlFacadeFactory = subReplyCrawlFacadeFactory;
            _threadLateCrawlerAndSaverFactory = threadLateCrawlerAndSaverFactory;
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            _timer.Elapsed += async (_, _) => await CrawlThenSave();
            await CrawlThenSave();
        }

        private async Task CrawlThenSave()
        {
            try
            {
                await using var db = _dbContextFactory(0);
                var forums = from f in db.ForumsInfo where f.IsCrawling select new {f.Fid, f.Name};
                await Task.WhenAll(forums.ToList().Select(async fidAndName =>
                {
                    var fid = fidAndName.Fid;
                    await CrawlSubReplies(await CrawlReplies(await CrawlThreads(fidAndName.Name, fid), fid), fid);
                }));
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
            if (!_latestReplyTimeCheckpointCache.TryGetValue(fid, out var timeInPreviousCrawl))
                // get the largest value of field latestReplyTime in all stored threads of this forum
                // this approach is not as accurate as extracting the last thread in the response list and needs a full table scan on db
                // https://stackoverflow.com/questions/341264/max-or-default
                timeInPreviousCrawl = _dbContextFactory(fid).Threads.Max(t => (Time?)t.LatestReplyTime) ?? Time.MaxValue;
            do
            {
                crawlingPage++;
                var crawler = _threadCrawlFacadeFactory(fid, forumName);
                savedThreads.AddIfNotNull((await crawler.CrawlPageRange(crawlingPage, crawlingPage)).SavePosts());
                if (crawler.FirstAndLastPostInPages.TryGetValue(crawlingPage, out var threadsTuple))
                {
                    var (firstThread, lastThread) = threadsTuple;
                    lastThreadTime = lastThread.LatestReplyTime;
                    if (crawlingPage == 1) _latestReplyTimeCheckpointCache[fid] = firstThread.LatestReplyTime;
                }
                else
                {// retry this page
                    crawlingPage--;
                    lastThreadTime = Time.MaxValue;
                }
            } while (lastThreadTime > timeInPreviousCrawl);

            await Task.WhenAll(savedThreads.Select(threads =>
                _threadLateCrawlerAndSaverFactory(fid).Crawl(threads.NewlyAdded.Select(t =>
                    new ThreadLateCrawlerAndSaver.TidAndFailedCount(t.Tid, 0)))));

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
                var crawler = _replyCrawlFacadeFactory(fid, tid);
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
                _ = (await _subReplyCrawlFacadeFactory(fid, tid, pid).CrawlPageRange(1)).SavePosts();
            }));
        }
    }
}
