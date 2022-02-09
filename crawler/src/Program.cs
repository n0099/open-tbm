using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Threading;
using System.Threading.Tasks;
using Autofac;
using Autofac.Extensions.DependencyInjection;
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
                    .ConfigureServices((_, service) =>
                    {
                        service.AddHostedService<Worker>();
                    })
                    .UseServiceProviderFactory(new AutofacServiceProviderFactory())
                    .ConfigureContainer((ContainerBuilder builder) =>
                    {
                        builder.RegisterType<TbmDbContext>();
                        builder.RegisterType<ClientRequester>();
                        builder.RegisterType<ClientRequesterTcs>().SingleInstance();
                        builder.RegisterType<CrawlerLocks>().Keyed<CrawlerLocks>("thread").SingleInstance();
                        builder.RegisterType<CrawlerLocks>().Keyed<CrawlerLocks>("reply").SingleInstance();
                        builder.RegisterType<CrawlerLocks>().Keyed<CrawlerLocks>("subReply").SingleInstance();
                        builder.RegisterType<UserParser>();

                        void RegisterPostCrawler<T>(string clientVersion) where T : notnull =>
                            builder.RegisterType<T>().WithParameter(
                                (p, _) => p.ParameterType == typeof(ClientRequester),
                                (_, c) => c.Resolve<ClientRequester.New>()(clientVersion));
                        RegisterPostCrawler<ThreadCrawler>("6.0.2");
                        RegisterPostCrawler<ReplyCrawler>("8.8.8");
                        RegisterPostCrawler<SubReplyCrawler>("8.8.8");
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
