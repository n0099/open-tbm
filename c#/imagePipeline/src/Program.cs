using Autofac.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection.Extensions;
using Microsoft.Extensions.Http;
using NLog;
using NLog.Extensions.Logging;
using tbm.ImagePipeline;
using tbm.ImagePipeline.Ocr;

#pragma warning disable IDE0058 // Expression value is never used

var logger = LogManager.GetCurrentClassLogger();
AppDomain.CurrentDomain.UnhandledException += (_, args) =>
    logger.Error((Exception)args.ExceptionObject, "AppDomain.UnhandledException:");
TaskScheduler.UnobservedTaskException += (_, args) =>
    logger.Error(args.Exception, "TaskScheduler.UnobservedTaskException:");
try
{
    var host = Host.CreateDefaultBuilder()
        .ConfigureLogging((_, logging) =>
        {
            logging.ClearProviders();
            logging.AddNLog(new NLogProviderOptions {RemoveLoggerFactoryFilter = false});
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

static void ConfigureServices(HostBuilderContext context, IServiceCollection service)
{
    service.AddHostedService<ImageOcrPipelineWorker>();

    var imageOcrPipelineConfig = context.Configuration.GetSection("ImageOcrPipeline").GetSection("HttpClient");
    service.AddHttpClient("tbImage", client =>
        {
            client.BaseAddress = new("https://imgsrc.baidu.com/forum/pic/item/");
            client.Timeout = TimeSpan.FromMilliseconds(imageOcrPipelineConfig.GetValue("TimeoutMs", 3000));
        })
        .SetHandlerLifetime(TimeSpan.FromSeconds(imageOcrPipelineConfig.GetValue("HandlerLifetimeSec", 600))); // 10 mins

    service.RemoveAll<IHttpMessageHandlerBuilderFilter>(); // https://stackoverflow.com/questions/52889827/remove-http-client-logging-handler-in-asp-net-core/52970073#52970073
}

static void ConfigureContainer(ContainerBuilder builder)
{
    builder.RegisterType<ImagePipelineDbContext>();
    builder.RegisterType<PaddleOcrRecognizerAndDetector>();
    builder.RegisterType<TesseractRecognizer>();
    builder.RegisterType<ImageOcrConsumer>();
}
