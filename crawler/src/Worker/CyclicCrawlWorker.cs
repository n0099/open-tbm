namespace tbm.Crawler.Worker
{
    public abstract class CyclicCrawlWorker : BackgroundService
    {
        private readonly ILogger<CyclicCrawlWorker> _logger;
        private readonly IConfiguration _config;
        private Timer Timer { get; } = new() {Enabled = true};

        protected CyclicCrawlWorker(ILogger<CyclicCrawlWorker> logger, IConfiguration config)
        {
            _logger = logger;
            _config = config;
            _ = SyncCrawlIntervalWithConfig();
        }

        protected int SyncCrawlIntervalWithConfig()
        {
            var interval = _config.GetValue("CrawlInterval", 60);
            Timer.Interval = interval * 1000;
            return interval;
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            Timer.Elapsed += async (_, _) =>
                await ExceptionLogger(DoWork(stoppingToken));
            await ExceptionLogger(DoWork(stoppingToken));
        }

        private async Task ExceptionLogger(Task payload)
        {
            try
            {
                _ = SyncCrawlIntervalWithConfig();
                await payload;
            }
            catch (Exception e)
            {
                _logger.LogError(e, "Exception");
            }
        }

        protected abstract Task DoWork(CancellationToken stoppingToken);
    }
}
