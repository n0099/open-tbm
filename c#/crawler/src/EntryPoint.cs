using System.Net;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection.Extensions;
using Microsoft.Extensions.Http;

#pragma warning disable IDE0058 // Expression value is never used

namespace tbm.Crawler;

public class EntryPoint : BaseEntryPoint
{
    protected override void ConfigureServices(HostBuilderContext context, IServiceCollection service)
    {
        service.AddHostedService<ResumeSuspendPostContentsPushingWorker>();
        service.AddHostedService<MainCrawlWorker>();
        service.AddHostedService<RetryCrawlWorker>();
        service.AddHostedService<ForumModeratorRevisionCrawlWorker>();

        var clientRequesterConfig = context.Configuration.GetSection("ClientRequester");
        service.AddHttpClient("tbClient", client =>
            {
                client.BaseAddress = new(ClientRequester.ClientApiDomain);
                client.Timeout = TimeSpan.FromMilliseconds(clientRequesterConfig.GetValue("TimeoutMs", 3000));
            })
            .SetHandlerLifetime(TimeSpan.FromSeconds(clientRequesterConfig.GetValue("HandlerLifetimeSec", 600))) // 10 mins
            .ConfigurePrimaryHttpMessageHandler(() => new HttpClientHandler {AutomaticDecompression = DecompressionMethods.GZip});

        // https://stackoverflow.com/questions/52889827/remove-http-client-logging-handler-in-asp-net-core/52970073#52970073
        service.RemoveAll<IHttpMessageHandlerBuilderFilter>();
    }

    protected override void ConfigureContainer(HostBuilderContext context, ContainerBuilder builder)
    {
        var baseClassOfClassesToBeRegistered = new[]
        {
            typeof(BaseCrawler<,>), typeof(BaseCrawlFacade<,,,>),
            typeof(BaseParser<,>), typeof(BaseSaver<,>)
        };
        builder.RegisterAssemblyTypes(Assembly.GetExecutingAssembly())
            .Where(type => Array.Exists(baseClassOfClassesToBeRegistered, baseType => baseType.IsSubTypeOfRawGeneric(type)))
            .AsSelf();

        builder.RegisterType<CrawlerDbContext>();
        builder.RegisterType<ClientRequester>();
        builder.RegisterType<ClientRequesterTcs>().SingleInstance();
        CrawlerLocks.RegisteredCrawlerLocks.ForEach(type =>
            builder.RegisterType<CrawlerLocks>()
                .Keyed<CrawlerLocks>(type)
                .WithParameter("lockType", type)
                .SingleInstance()
                .AsSelf()
                // eager initial all keyed CrawlerLocks singleton instances, in order to sync their timer of WithLogTrace
                .AutoActivate());
        builder.RegisterType<AuthorRevisionSaver>();
        builder.RegisterType<UserParserAndSaver>();
        builder.RegisterType<ThreadLateCrawlerAndSaver>();
        builder.RegisterType<ThreadArchiveCrawler>();
        builder.RegisterType<SonicPusher>();
    }
}
