namespace tbm.Crawler
{
    internal class Program
    {
        public static ILifetimeScope Autofac { get; private set; } = null!;
        public static readonly List<string> RegisteredCrawlerLocks = new() {"thread", "threadLate", "reply", "subReply"};

        private static void Main()
        {
            var logger = LogManager.GetCurrentClassLogger();
            AppDomain.CurrentDomain.UnhandledException += (_, args) =>
                logger.Error((Exception)args.ExceptionObject, "AppDomain.UnhandledException:");
            TaskScheduler.UnobservedTaskException += (_, args) =>
                logger.Error(args.Exception, "TaskScheduler.UnobservedTaskException:");
            try
            {
#pragma warning disable IDE0058 // Expression value is never used
                var host = Host.CreateDefaultBuilder()
                    .ConfigureLogging((_, logging) =>
                    {
                        logging.ClearProviders();
                        logging.AddNLog();
                    })
                    .ConfigureServices((_, service) =>
                    {
                        service.AddHostedService<MainCrawlWorker>();
                        service.AddHostedService<RetryCrawlWorker>();
                    })
                    .UseServiceProviderFactory(new AutofacServiceProviderFactory())
                    .ConfigureContainer((ContainerBuilder builder) =>
                    {
                        builder.RegisterType<TbmDbContext>();
                        builder.Register(c =>
                        {
                            var http = new ClientRequester.HttpClient();
                            var config = c.Resolve<IConfiguration>().GetSection("ClientRequester");
                            http.Timeout = TimeSpan.FromMilliseconds(config.GetValue("TimeoutMs", 3000));
                            return http;
                        }).AsSelf().SingleInstance();
                        builder.RegisterType<ClientRequester>().WithParameter(
                            (p, _) => p.ParameterType == typeof(ClientRequester.HttpClient),
                            (_, c) => c.Resolve<ClientRequester.HttpClient>());
                        builder.RegisterType<ClientRequesterTcs>().SingleInstance();
                        RegisteredCrawlerLocks.ForEach(l =>
                            builder.RegisterType<CrawlerLocks>().Keyed<CrawlerLocks>(l).SingleInstance());
                        builder.RegisterType<UserParserAndSaver>();

                        var baseClassOfClassesToBeRegister = new List<Type>
                        {
                            typeof(BaseCrawler<,>), typeof(BaseCrawlFacade<,,,>),
                            typeof(BaseParser<,>), typeof(BaseSaver<>)
                        };
                        builder.RegisterAssemblyTypes(Assembly.GetExecutingAssembly()).Where(t =>
                            baseClassOfClassesToBeRegister.Any(c => c.IsSubTypeOfRawGeneric(t))).AsSelf();
                    })
                    .Build();
                Autofac = host.Services.GetAutofacRoot();
                host.Run();
#pragma warning restore IDE0058 // Expression value is never used
            }
            catch (Exception e)
            {
                logger.Fatal(e, "exception");
            }
            finally
            {
                LogManager.Shutdown();
            }
        }
    }
}
