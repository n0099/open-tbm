namespace tbm.Crawler.Worker;

public abstract class CyclicCrawlWorker(bool shouldRunAtFirst = true)
    : ErrorableWorker
{
    private int _interval; // in seconds

    // ReSharper disable once UnusedAutoPropertyAccessor.Global
    public required IConfiguration Config { private get; init; }

    protected int SyncCrawlIntervalWithConfig()
    {
        _interval = Config.GetValue("CrawlInterval", 60);
        return _interval;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _ = SyncCrawlIntervalWithConfig();
        if (shouldRunAtFirst)
            await DoWorkWithExceptionLogging(stoppingToken);
        while (!stoppingToken.IsCancellationRequested)
        { // https://stackoverflow.com/questions/51667000/ihostedservice-backgroundservice-to-run-on-a-schedule-as-opposed-to-task-delay
            await Task.Delay(_interval * 1000, stoppingToken);
            _ = SyncCrawlIntervalWithConfig();
            await DoWorkWithExceptionLogging(stoppingToken);
        }
    }
}
