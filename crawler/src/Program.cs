using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace tbm
{
    class Program
    {
        static async Task Main()
        {
            AppDomain.CurrentDomain.UnhandledException += (_, args) =>
                Console.WriteLine($"ExceptionData={JsonSerializer.Serialize(((Exception)args.ExceptionObject).Data)}\n{args.ExceptionObject}");
            TaskScheduler.UnobservedTaskException += (_, args) =>
                Console.WriteLine($"ExceptionData={JsonSerializer.Serialize(args.Exception.Data)}\n{args.Exception}");

            var crawler = new ThreadCrawler(new ClientRequester("6.0.2"), "", 0, 1, 100);
            await crawler.DoCrawler();
        }

        static void RpsIndicator()
        {
            var requester = new ClientRequester("6.0.2");
            var requestParams = new Dictionary<string, string> {{"kw", ""}, {"pn", "1"}, {"rn", "50"}};
            var timer = new Stopwatch();
            timer.Start();
            var requestedTimes = 0;
            var failedRequests = 0;
            var successRequests = 0;
            Process proc = Process.GetCurrentProcess();
            while (true)
            {
                Thread.Sleep(1);
                /*
                var crawler = new ThreadCrawler(ClientRequesterThrottler.Bind(requester), "", 0, 1);
                _ = crawler.DoCrawler();
                */
                var response = requester.Post("http://c.tieba.baidu.com/c/f/frs/page", requestParams);
                response.ContinueWith(i =>
                {
                    Console.WriteLine(ClientRequesterTcs.QueueLength);
                    // i.Result.Content.ReadAsStringAsync().ContinueWith(i => Console.WriteLine(i.Result.Length));
                    if (i.Result.IsSuccessStatusCode) successRequests++;
                    else failedRequests++;
                    i.Result.Dispose();
                });
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
