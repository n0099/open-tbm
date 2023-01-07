namespace tbm.Crawler.Worker
{
    public class ForumModeratorRevisionCrawlWorker : CyclicCrawlWorker
    {
        private readonly ILogger<MainCrawlWorker> _logger;
        private readonly ILifetimeScope _scope0;

        public ForumModeratorRevisionCrawlWorker(ILogger<MainCrawlWorker> logger,
            IConfiguration config, ILifetimeScope scope0) : base(config)
        {
            _logger = logger;
            _scope0 = scope0;
            _ = SyncCrawlIntervalWithConfig();
        }

        protected override Task ExecuteAsync(CancellationToken stoppingToken)
        {
            Timer.Elapsed += async (_, _) => await CrawlThenSave();
            return Task.CompletedTask;
        }

        private async Task CrawlThenSave()
        {

        }
    }
}
