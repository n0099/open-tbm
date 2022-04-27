namespace tbm.Crawler
{
    using SavedThreads = List<ReturnOfSaver<ThreadPost>>;
    using SavedRepliesByTid = ConcurrentDictionary<Tid, ReturnOfSaver<ReplyPost>>;

    public class MainCrawlWorker : BackgroundService
    {
        private readonly ILogger<MainCrawlWorker> _logger;
        private readonly Timer _timer = new() {Enabled = true, Interval = 60 * 1000}; // per minute
        // stores the latestReplyTime of first thread appears in the page of previous crawl worker, key by fid
        private readonly Dictionary<Fid, Time> _latestReplyTimeCheckpointCache = new();

        public MainCrawlWorker(ILogger<MainCrawlWorker> logger) => _logger = logger;

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            _timer.Elapsed += async (_, _) => await CrawlThenSave();
            await CrawlThenSave();
        }

        private async Task CrawlThenSave()
        {
            await using var scope = Program.Autofac.BeginLifetimeScope();
            try
            {
                var db = scope.Resolve<TbmDbContext.New>()(0);
                var forums = from f in db.ForumsInfo where f.IsCrawling == true select new {f.Fid, f.Name};
                await Task.WhenAll(forums.ToList().Select(async fidAndName =>
                {
                    var fid = fidAndName.Fid;
                    await CrawlSubReplies(await CrawlReplies(await CrawlThreads(fidAndName.Name, fid), fid), fid);
                }));
            }
            catch (Exception e)
            {
                _logger.LogError("exception: {}", e);
            }
        }

        private async Task<SavedThreads> CrawlThreads(string forumName, Fid fid)
        {
            await using var scope = Program.Autofac.BeginLifetimeScope();
            var savedThreads = new SavedThreads();
            Time lastThreadTime;
            Page crawlingPage = 0;
            if (!_latestReplyTimeCheckpointCache.TryGetValue(fid, out var timeInPreviousCrawl))
                // get the largest value of field latestReplyTime in all stored threads of this forum
                // this approach is not as accurate as extracting the last thread in the response list
                // https://stackoverflow.com/questions/341264/max-or-default
                timeInPreviousCrawl = scope.Resolve<TbmDbContext.New>()(fid).Threads.Max(t => (Time?)t.LatestReplyTime) ?? Time.MaxValue;
            do
            {
                crawlingPage++;
                await using var crawlScope = scope.BeginLifetimeScope();
                var crawler = crawlScope.Resolve<ThreadCrawlFacade.New>()(fid, forumName);
                savedThreads.AddIfNotNull((await crawler.CrawlPageRange(crawlingPage, crawlingPage)).SavePosts());

                var (firstThread, lastThread) = crawler.FirstAndLastPostInPages[crawlingPage];
                lastThreadTime = lastThread.LatestReplyTime;
                if (crawlingPage == 1)
                    _latestReplyTimeCheckpointCache[fid] = firstThread.LatestReplyTime;
            } while (lastThreadTime > timeInPreviousCrawl);

            return savedThreads;
        }

        private static async Task<SavedRepliesByTid> CrawlReplies(SavedThreads savedThreads, Fid fid)
        {
            await using var scope = Program.Autofac.BeginLifetimeScope();
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
                await using var crawlScope = scope.BeginLifetimeScope();
                var crawler = crawlScope.Resolve<ReplyCrawlFacade.New>()(fid, tid);
                savedRepliesByTid.SetIfNotNull(tid, (await crawler.CrawlPageRange(1)).SavePosts());
            }));
            return savedRepliesByTid;
        }

        private static async Task CrawlSubReplies(SavedRepliesByTid savedRepliesByTid, Fid fid)
        {
            await using var scope = Program.Autofac.BeginLifetimeScope();
            var shouldCrawlSubReplyPid = savedRepliesByTid.Aggregate(new HashSet<(Tid tid, Pid pid)>(), (shouldCrawl, tidAndReplies) =>
            {
                var (tid, replies) = tidAndReplies;
                replies.NewlyAdded.ForEach(i => shouldCrawl.Add((tid, i.Pid)));
                replies.Existing.ForEach(beforeAndAfter =>
                {
                    var (before, after) = beforeAndAfter;
                    if (before.SubReplyNum != after.SubReplyNum) _ = shouldCrawl.Add((tid, before.Pid));
                });
                return shouldCrawl;
            });
            await Task.WhenAll(shouldCrawlSubReplyPid.Select(async tidAndPid =>
            {
                var (tid, pid) = tidAndPid;
                await using var crawlScope = scope.BeginLifetimeScope();
                _ = (await crawlScope.Resolve<SubReplyCrawlFacade.New>()(fid, tid, pid).CrawlPageRange(1)).SavePosts();
            }));
        }
    }
}
