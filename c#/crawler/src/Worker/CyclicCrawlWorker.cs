namespace tbm.Crawler.Worker;

public abstract class CyclicCrawlWorker : ErrorableWorker
{
    private readonly IConfiguration _config;
    private int _interval; // in seconds
    private readonly bool _shouldRunAtFirst;

    protected CyclicCrawlWorker
        (ILogger<CyclicCrawlWorker> logger, IConfiguration config, bool shouldRunAtFirst = true) : base(logger)
    {
        (_config, _shouldRunAtFirst) = (config, shouldRunAtFirst);
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
        {
            _ = SyncCrawlIntervalWithConfig();
            await DoWorkWithExceptionLogging(stoppingToken);
        }
        while (!stoppingToken.IsCancellationRequested)
        { // https://stackoverflow.com/questions/51667000/ihostedservice-backgroundservice-to-run-on-a-schedule-as-opposed-to-task-delay
            await Task.Delay(_interval * 1000, stoppingToken);
            _ = SyncCrawlIntervalWithConfig();
            await DoWorkWithExceptionLogging(stoppingToken);
        }
    }
}
