using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace tbm
{
    internal class Program
    {
        private static async Task Main()
        {
            AppDomain.CurrentDomain.UnhandledException += (_, args) =>
                Console.WriteLine($"ExceptionData={JsonSerializer.Serialize(((Exception)args.ExceptionObject).Data)}\n{args.ExceptionObject}");
            TaskScheduler.UnobservedTaskException += (_, args) => LogException(args.Exception);

            var crawler = new ThreadCrawler(new ClientRequester("6.0.2"), 0, "");
            await Task.WhenAll(new List<Task>() { crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100) });
            Thread.Sleep(1000);
            _ = Task.WhenAll(new List<Task>() { crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100) });
            Thread.Sleep(2000);

            await Task.WhenAll(Enumerable.Range(0, 10).Select(i => new ThreadCrawler(new ClientRequester("6.0.2"), 0, "").DoCrawler(1, 100)));
        }

        public static void LogException(Exception e) =>
            Console.WriteLine($"ExceptionData={JsonSerializer.Serialize(e.Data)}\n{e}");

        private static void RpsIndicator()
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
                var crawler = new ThreadCrawler(requester, 0, "");
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
