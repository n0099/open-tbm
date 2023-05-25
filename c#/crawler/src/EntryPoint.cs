using System.Net;
using System.Reflection;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection.Extensions;
using Microsoft.Extensions.Http;
using tbm.Crawler.Worker;

#pragma warning disable IDE0058

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
                client.BaseAddress = new("http://c.tieba.baidu.com");
                client.Timeout = TimeSpan.FromMilliseconds(clientRequesterConfig.GetValue("TimeoutMs", 3000));
            })
            .SetHandlerLifetime(TimeSpan.FromSeconds(clientRequesterConfig.GetValue("HandlerLifetimeSec", 600))) // 10 mins
            .ConfigurePrimaryHttpMessageHandler(() => new HttpClientHandler {AutomaticDecompression = DecompressionMethods.GZip});

        // https://stackoverflow.com/questions/52889827/remove-http-client-logging-handler-in-asp-net-core/52970073#52970073
        service.RemoveAll<IHttpMessageHandlerBuilderFilter>();
    }

    protected override void ConfigureContainer(ContainerBuilder builder)
    {
        builder.RegisterType<CrawlerDbContext>();
        builder.RegisterType<ClientRequester>();
        builder.RegisterType<ClientRequesterTcs>().SingleInstance();
        CrawlerLocks.RegisteredCrawlerLocks.ForEach(type =>
            builder.RegisterType<CrawlerLocks>()
                .Keyed<CrawlerLocks>(type)
                .SingleInstance()
                .WithParameter("lockType", type));
        builder.RegisterType<AuthorRevisionSaver>();
        builder.RegisterType<UserParserAndSaver>();
        builder.RegisterType<ThreadLateCrawlerAndSaver>();
        builder.RegisterType<ThreadArchiveCrawler>();
        builder.RegisterType<SonicPusher>();

        var baseClassOfClassesToBeRegistered = new[]
        {
            typeof(BaseCrawler<,>), typeof(BaseCrawlFacade<,,,,>),
            typeof(BaseParser<,>), typeof(BaseSaver<,>)
        };
        builder.RegisterAssemblyTypes(Assembly.GetExecutingAssembly())
            .Where(type => baseClassOfClassesToBeRegistered.Any(baseType => baseType.IsSubTypeOfRawGeneric(type)))
            .AsSelf();
    }
}
