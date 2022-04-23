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
            await using var rootScope = Program.Autofac.BeginLifetimeScope();
            try
            {
                var db = rootScope.Resolve<TbmDbContext.New>()(0);
                var forums = from f in db.ForumsInfo where f.IsCrawling == true select new {f.Fid, f.Name};
                await Task.WhenAll(forums.ToList().Select(async fidAndName =>
                {
                    await using var scope = Program.Autofac.BeginLifetimeScope();
                    var fid = fidAndName.Fid;
                    var forumName = fidAndName.Name;
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
                        (await crawler.CrawlPageRange(crawlingPage, crawlingPage)).SavePosts(out var _);

                        var (firstThread, lastThread) = crawler.FirstAndLastPostInPages[crawlingPage];
                        lastThreadTime = lastThread.LatestReplyTime;
                        if (crawlingPage == 1)
                            _latestReplyTimeCheckpointCache[fid] = firstThread.LatestReplyTime;
                    } while (lastThreadTime > timeInPreviousCrawl);
                }));
            }
            catch (Exception e)
            {
                _logger.LogError("exception: {}", e);
            }
        }
    }
}
