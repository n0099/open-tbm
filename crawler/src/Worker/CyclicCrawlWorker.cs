namespace tbm.Crawler.Worker
{
    public abstract class CyclicCrawlWorker : BackgroundService
    {
        private readonly ILogger<CyclicCrawlWorker> _logger;
        private readonly IConfiguration _config;
        private int _interval; // in seconds
        private readonly bool _shouldRunAtFirst;

        protected CyclicCrawlWorker(ILogger<CyclicCrawlWorker> logger, IConfiguration config, bool shouldRunAtFirst = true)
        {
            _logger = logger;
            _config = config;
            _shouldRunAtFirst = shouldRunAtFirst;
            _ = SyncCrawlIntervalWithConfig();
        }

        protected int SyncCrawlIntervalWithConfig()
        {
            _interval = _config.GetValue("CrawlInterval", 60);
            return _interval;
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            if (_shouldRunAtFirst)
                await LogException(DoWork(stoppingToken), stoppingToken);
            while (!stoppingToken.IsCancellationRequested)
            {
                await Task.Delay(_interval * 1000, stoppingToken);
                await LogException(DoWork(stoppingToken), stoppingToken);
            }
        }

        private async Task LogException(Task payload, CancellationToken stoppingToken)
        {
            try
            {
                _ = SyncCrawlIntervalWithConfig();
                await payload;
            }
            catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
            {
                _logger.LogInformation($"OperationCanceledException at {e.Source}");
            }
            catch (Exception e)
            {
                _logger.LogError(e, "Exception");
            }
        }

        protected abstract Task DoWork(CancellationToken stoppingToken);
    }
}
