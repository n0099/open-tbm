using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection.Extensions;
using Microsoft.Extensions.Http;
#pragma warning disable IDE0058

namespace tbm.ImagePipeline;

public class EntryPoint : BaseEntryPoint
{
    protected override void ConfigureServices(HostBuilderContext context, IServiceCollection service)
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

    protected override void ConfigureContainer(ContainerBuilder builder)
    {
        builder.RegisterType<ImagePipelineDbContext>();
        builder.RegisterType<PaddleOcrRecognizerAndDetector>();
        builder.RegisterType<TesseractRecognizer>();
        builder.RegisterType<ImageOcrConsumer>();
    }
}
