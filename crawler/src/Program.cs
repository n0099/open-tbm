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

namespace tbm
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
                    .UseServiceProviderFactory(new AutofacServiceProviderFactory())
                    .ConfigureServices((_, service) =>
                    {
                        service.AddHostedService<Worker>();
                    })
                    .ConfigureContainer((ContainerBuilder builder) =>
                    {
                        builder.RegisterType<DbContext>();
                        builder.RegisterType<ClientRequester>();
                        builder.RegisterType<CrawlerLocks>().Keyed<CrawlerLocks>("thread").SingleInstance();
                        builder.RegisterType<ThreadCrawler>().WithParameter(
                            (p, _) => p.ParameterType == typeof(ClientRequester),
                            (_, c) => c.Resolve<ClientRequester.New>()("6.0.2"));
                    })
                    .Build();
                Autofac = host.Services.GetAutofacRoot();
                host.Run();
                host.WaitForShutdown();
            }
            catch (Exception e)
            {
                logger.Fatal(e, "exception: ");
                throw;
            }
            finally
            {
                LogManager.Shutdown();
            }
        }

        public static void RpsIndicator()
        {
            var requester = new ClientRequester("6.0.2");
            var requestParams = new Dictionary<string, string> {{"kw", ""}, {"pn", "1"}, {"rn", "50"}};
            var timer = new Stopwatch();
            timer.Start();
            var requestedTimes = 0;
            var failedRequests = 0;
            var successRequests = 0;
            var proc = Process.GetCurrentProcess();
            while (true)
            {
                Thread.Sleep(1);
                var crawler = Autofac.Resolve<ThreadCrawler.New>()(0, "");
                _ = crawler.DoCrawler(1, 100);
                /*
                var response = requester.Post("http://c.tieba.baidu.com/c/f/frs/page", requestParams);
                response.ContinueWith(i =>
                {
                    // i.Result.Content.ReadAsStringAsync().ContinueWith(i => Console.WriteLine(i.Result.Length));
                    if (i.Result.IsSuccessStatusCode) successRequests++;
                    else failedRequests++;
                    i.Result.Dispose();
                });
                */
                requestedTimes++;
                // if (response.StatusCode != HttpStatusCode.OK) Console.WriteLine(response.StatusCode);
                // var body = await response.Content.ReadAsStringAsync();
                var elapsedSec = timer.Elapsed.Seconds;
                if (elapsedSec < 1) continue;

                timer.Restart();
                proc.Refresh();
                Console.WriteLine($"rps={requestedTimes} success={successRequests} failed={failedRequests} mem={proc.PrivateMemorySize64 / 1024 / 1024}MB");
                requestedTimes = 0;
                failedRequests = 0;
                successRequests = 0;
            }
        }
    }
}
