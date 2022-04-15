using System;
using System.Threading.Tasks;
using Autofac;
using Autofac.Extensions.DependencyInjection;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using NLog;
using NLog.Extensions.Logging;

namespace tbm.Crawler
{
    internal class Program
    {
        public static ILifetimeScope Autofac { get; private set; } = null!;

        private static void Main()
        {
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
                        logging.AddNLog();
                    })
                    .ConfigureServices((_, service) => service.AddHostedService<Worker>())
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
                        builder.RegisterType<CrawlerLocks>().Keyed<CrawlerLocks>("thread").SingleInstance();
                        builder.RegisterType<CrawlerLocks>().Keyed<CrawlerLocks>("reply").SingleInstance();
                        builder.RegisterType<CrawlerLocks>().Keyed<CrawlerLocks>("subReply").SingleInstance();
                        builder.RegisterType<UserParserAndSaver>();
                    })
                    .Build();
                Autofac = host.Services.GetAutofacRoot();
                host.Run();
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
