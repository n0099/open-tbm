namespace tbm.Crawler.Worker
{
    public abstract class CyclicCrawlWorker : BackgroundService
    {
        private readonly IConfiguration _config;
        protected Timer Timer { get; } = new() {Enabled = true};

        protected CyclicCrawlWorker(IConfiguration config) => _config = config;

        protected int SyncCrawlIntervalWithConfig()
        {
            var interval = _config.GetValue("CrawlInterval", 60);
            Timer.Interval = interval * 1000;
            return interval;
        }
    }
}
