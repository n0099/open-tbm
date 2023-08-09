using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace tbm.Shared;

public abstract class ErrorableWorker(
        ILogger<ErrorableWorker> logger,
        IHostApplicationLifetime applicationLifetime,
        bool shouldExitOnException = false,
        bool shouldExitOnFinish = false)
    : BackgroundService
{
    protected override Task ExecuteAsync(CancellationToken stoppingToken) => DoWorkWithExceptionLogging(stoppingToken);

    protected abstract Task DoWork(CancellationToken stoppingToken);

    protected async Task DoWorkWithExceptionLogging(CancellationToken stoppingToken)
    {
        try
        {
            await Task.Yield(); // https://blog.stephencleary.com/2020/05/backgroundservice-gotcha-startup.html
            await DoWork(stoppingToken);
            // https://blog.stephencleary.com/2020/06/backgroundservice-gotcha-application-lifetime.html
            if (shouldExitOnFinish) applicationLifetime.StopApplication();
        }
        catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
        {
            logger.LogInformation("{}: {} CancellationToken={}",
                e.GetType().FullName, e.Message, e.CancellationToken);
        }
        catch (Exception e)
        {
            logger.LogError(e, "Exception");
            // https://stackoverflow.com/questions/68387710/exit-the-application-with-exit-code-from-async-thread
            Environment.ExitCode = 1;
            if (shouldExitOnException) applicationLifetime.StopApplication();
        }
    }
}
