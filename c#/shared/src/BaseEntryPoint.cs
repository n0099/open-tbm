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
    protected abstract void ConfigureServices(HostBuilderContext context, IServiceCollection service);
    protected abstract void ConfigureContainer(HostBuilderContext context, ContainerBuilder builder);

    public async Task Main()
    {
        var logger = LogManager.GetCurrentClassLogger();
        AppDomain.CurrentDomain.UnhandledException += (_, args) =>
            logger.Error((Exception)args.ExceptionObject, "AppDomain.UnhandledException:");
        TaskScheduler.UnobservedTaskException += (_, args) =>
            logger.Error(args.Exception, "TaskScheduler.UnobservedTaskException:");
        try
        {
            var host = Host.CreateDefaultBuilder()
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
}
