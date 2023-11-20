using Autofac;
using Autofac.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using NLog;
using NLog.Extensions.Logging;

namespace tbm.Shared;

public abstract class BaseEntryPoint
{
    public async Task Main(string[] args)
    {
        var logger = LogManager.GetCurrentClassLogger();
        AppDomain.CurrentDomain.UnhandledException += (_, eventArgs) =>
            logger.Error((Exception)eventArgs.ExceptionObject, "AppDomain.UnhandledException:");
        TaskScheduler.UnobservedTaskException += (_, eventArgs) =>
            logger.Error(eventArgs.Exception, "TaskScheduler.UnobservedTaskException:");
        try
        {
            var host = Host.CreateDefaultBuilder(args)
                .ConfigureLogging((__, logging) =>
                {
                    _ = logging.ClearProviders();
                    _ = logging.AddNLog(new NLogProviderOptions {RemoveLoggerFactoryFilter = false});
                })
                .ConfigureServices(ConfigureServices)
                .UseServiceProviderFactory(new AutofacServiceProviderFactory())
                .ConfigureContainer<ContainerBuilder>(ConfigureContainer)
                .Build();
            await host.RunAsync();
        }
        catch (Exception e)
        {
            logger.Fatal(e, "Exception");
        }
        finally
        {
            LogManager.Shutdown();
        }
    }

    protected abstract void ConfigureServices(HostBuilderContext context, IServiceCollection service);
    protected abstract void ConfigureContainer(HostBuilderContext context, ContainerBuilder builder);
}
