using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace tbm.Shared;

public abstract class ErrorableWorker(bool shouldExitOnException = false, bool shouldExitOnFinish = false)
    : BackgroundService
{
    // ReSharper disable UnusedAutoPropertyAccessor.Global
    public required ILogger<ErrorableWorker> Logger { private get; init; }
    public required IHostApplicationLifetime ApplicationLifetime { private get; init; }

    // ReSharper restore UnusedAutoPropertyAccessor.Global
    protected override Task ExecuteAsync(CancellationToken stoppingToken) =>
        DoWorkWithExceptionLogging(stoppingToken);
    protected abstract Task DoWork(CancellationToken stoppingToken);
    protected async Task DoWorkWithExceptionLogging(CancellationToken stoppingToken)
    {
        try
        {
            await Task.Yield(); // https://blog.stephencleary.com/2020/05/backgroundservice-gotcha-startup.html
            await DoWork(stoppingToken);

            // https://blog.stephencleary.com/2020/06/backgroundservice-gotcha-application-lifetime.html
            if (shouldExitOnFinish) ApplicationLifetime.StopApplication();
        }
        catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
        {
            Logger.LogInformation("{}: {} CancellationToken={}",
                e.GetType().FullName, e.Message, e.CancellationToken);
        }
        catch (Exception e)
        {
            Logger.LogError(e, "Exception");

            // https://stackoverflow.com/questions/68387710/exit-the-application-with-exit-code-from-async-thread
            Environment.ExitCode = 1;
            if (shouldExitOnException) ApplicationLifetime.StopApplication();
        }
    }
}
