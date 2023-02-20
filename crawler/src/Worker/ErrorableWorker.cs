namespace tbm.Crawler.Worker;

public abstract class ErrorableWorker : BackgroundService
{
    private readonly ILogger<ErrorableWorker> _logger;

    protected ErrorableWorker(ILogger<ErrorableWorker> logger) => _logger = logger;

    protected override Task ExecuteAsync(CancellationToken stoppingToken) => DoWorkWithExceptionLogging(stoppingToken);

    protected abstract Task DoWork(CancellationToken stoppingToken);

    protected async Task DoWorkWithExceptionLogging(CancellationToken stoppingToken)
    {
        try
        {
            await DoWork(stoppingToken);
        }
        catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
        {
            _logger.LogInformation("OperationCanceledException at {}", e.Source);
        }
        catch (Exception e)
        {
            _logger.LogError(e, "Exception");
        }
    }
}
