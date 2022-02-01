using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using Autofac;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace tbm.Crawler
{
    public class Worker : BackgroundService
    {
        private readonly ILogger<Worker> _logger;

        public Worker(ILogger<Worker> logger) => _logger = logger;

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            await using var scope = Program.Autofac.BeginLifetimeScope();

            var db = scope.Resolve<DbContext.New>()(0);
            var a = from thread in db.Threads orderby thread.Tid select thread.Title;
            _logger.LogInformation(JsonSerializer.Serialize(a.Take(5).ToList()));

            var crawler = scope.Resolve<ThreadCrawler.New>()(0, "");
            await Task.WhenAll(new List<Task>() { crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100), crawler.DoCrawler(1, 100) });
            Thread.Sleep(1000);
        }
    }
}
