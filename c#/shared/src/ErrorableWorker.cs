using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace tbm.Shared;

public abstract class ErrorableWorker : BackgroundService
{
    private readonly ILogger<ErrorableWorker> _logger;
    private readonly IHostApplicationLifetime _applicationLifetime;
    private readonly bool _shouldExitOnException;
    private readonly bool _shouldExitOnFinish;

    protected ErrorableWorker(
        ILogger<ErrorableWorker> logger,
        IHostApplicationLifetime applicationLifetime,
        bool shouldExitOnException = false,
        bool shouldExitOnFinish = false) =>
        (_logger, _applicationLifetime, _shouldExitOnException, _shouldExitOnFinish) =
        (logger, applicationLifetime, shouldExitOnException, shouldExitOnFinish);

    protected override Task ExecuteAsync(CancellationToken stoppingToken) => DoWorkWithExceptionLogging(stoppingToken);

    protected abstract Task DoWork(CancellationToken stoppingToken);

    protected async Task DoWorkWithExceptionLogging(CancellationToken stoppingToken)
    {
        try
        {
            await Task.Yield(); // https://blog.stephencleary.com/2020/05/backgroundservice-gotcha-startup.html
            await DoWork(stoppingToken);
            // https://blog.stephencleary.com/2020/06/backgroundservice-gotcha-application-lifetime.html
            if (_shouldExitOnFinish) _applicationLifetime.StopApplication();
        }
        catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
        {
            _logger.LogInformation("{}: {} CancellationToken={}",
                e.GetType().FullName, e.Message, e.CancellationToken);
        }
        catch (Exception e)
        {
            _logger.LogError(e, "Exception");
            // https://stackoverflow.com/questions/68387710/exit-the-application-with-exit-code-from-async-thread
            Environment.ExitCode = 1;
            if (_shouldExitOnException) _applicationLifetime.StopApplication();
        }
    }
}
