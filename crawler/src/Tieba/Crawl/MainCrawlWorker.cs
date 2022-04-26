namespace tbm.Crawler
{
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
                    var flatSavedThreads = (await CrawlThreads(fid, fidAndName.Name)).Select(i => i.Value);
                    await CrawlReplies(fid, flatSavedThreads);
                }));
            }
            catch (Exception e)
            {
                _logger.LogError("exception: {}", e);
            }
        }

        private async Task<Dictionary<Page, ReturnOfSaver<ThreadPost>>> CrawlThreads(Fid fid, string forumName)
        {
            await using var scope = Program.Autofac.BeginLifetimeScope();
            var savedPostsByPage = new Dictionary<Page, ReturnOfSaver<ThreadPost>>();
            Time lastThreadTime;
            Page crawlingPage = 0;
            if (!_latestReplyTimeCheckpointCache.TryGetValue(fid, out var timeInPreviousCrawl))
                // get the largest value of field latestReplyTime in all stored threads of this forum
                // this approach is not as accurate as extracting the last thread in the response list
                timeInPreviousCrawl = scope.Resolve<TbmDbContext.New>()(fid).Threads.Max(t => t.LatestReplyTime);
            do
            {
                crawlingPage++;
                await using var crawlScope = scope.BeginLifetimeScope();
                var crawler = crawlScope.Resolve<ThreadCrawlFacade.New>()(fid, forumName);
                savedPostsByPage.SetIfNotNull(crawlingPage,
                    (await crawler.CrawlPageRange(crawlingPage, crawlingPage)).SavePosts());

                var (firstThread, lastThread) = crawler.FirstAndLastPostInPages[crawlingPage];
                lastThreadTime = lastThread.LatestReplyTime;
                if (crawlingPage == 1)
                    _latestReplyTimeCheckpointCache[fid] = firstThread.LatestReplyTime;
            } while (lastThreadTime > timeInPreviousCrawl);

            return savedPostsByPage;
        }

        private static async Task<IEnumerable<ReturnOfSaver<ReplyPost>>>
            CrawlReplies(Fid fid, IEnumerable<ReturnOfSaver<ThreadPost>> savedThreads)
        {
            await using var scope = Program.Autofac.BeginLifetimeScope();
            var shouldCrawlReplyTid = savedThreads.Aggregate(new HashSet<Tid>(), (shouldCrawl, threads) =>
            {
                threads.NewlyAdded.ForEach(i => shouldCrawl.Add(i.Tid));
                threads.Existing.ForEach(beforeAndAfter =>
                {
                    var (before, after) = beforeAndAfter;
                    if (before.ReplyNum < after.ReplyNum
                        || before.LatestReplyTime < after.LatestReplyTime
                        || before.LatestReplierUid != after.LatestReplierUid)
                        _ = shouldCrawl.Add(before.Tid);
                });
                return shouldCrawl;
            });
            var savedPosts = new List<ReturnOfSaver<ReplyPost>>();
            await Task.WhenAll(shouldCrawlReplyTid.Select(async tid =>
            {
                await using var crawlScope = scope.BeginLifetimeScope();
                var crawler = crawlScope.Resolve<ReplyCrawlFacade.New>()(fid, tid);
                savedPosts.AddIfNotNull((await crawler.CrawlPageRange(1)).SavePosts());
            }));
            return savedPosts;
        }
    }
}
