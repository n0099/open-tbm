using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Threading;
using System.Threading.Tasks;

namespace tbm
{
    class Program
    {
        static async Task Main()
        {
            var crawler = new ThreadCrawler(new ClientRequester("6.0.2"), "", 0, 1);
            await crawler.DoCrawler();
        }

        static void RpsIndicator()
        {
            var requester = new ClientRequester("6.0.2");
            var requestParams = new Dictionary<string, string>() {{"kw", ""}, {"pn", "1"}, {"rn", "50"}};
            var timer = new Stopwatch();
            timer.Start();
            var requestedTimes = 0;
            var failedRequests = 0;
            var successRequests = 0;
            Process proc = Process.GetCurrentProcess();
            while (true)
            {
                Thread.Sleep(10);
                var response = requester.Post("https://c.tieba.baidu.com/c/f/frs/page", requestParams);
                _ = response.ContinueWith(i =>
                {
                    if (i.Result.IsSuccessStatusCode) successRequests++;
                    else failedRequests++;
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
