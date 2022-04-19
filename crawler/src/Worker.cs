using System;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using Autofac;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using Timer = System.Timers.Timer;

namespace tbm.Crawler
{
    public class Worker : BackgroundService
    {
        private readonly ILogger<Worker> _logger;
        private readonly Timer _timer = new() {Enabled = true, Interval = 60 * 1000}; // per minute

        public Worker(ILogger<Worker> logger) => _logger = logger;

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            _timer.Elapsed += async (_, _) => await CrawlThenSave();
            await CrawlThenSave();
        }

        private async Task CrawlThenSave()
        {
            await using var scope = Program.Autofac.BeginLifetimeScope();
            try
            {
                var crawler = scope.Resolve<ThreadCrawlFacade.New>()(0, "");
                (await crawler.CrawlPageRange(1, 1)).SavePosts<ThreadRevision>(
                    out var existingOrNewPosts,
                    out var existingOrNewUsers,
                    out var revisions);
                _logger.LogInformation(JsonSerializer.Serialize(existingOrNewPosts));
                _logger.LogInformation(JsonSerializer.Serialize(existingOrNewUsers));
                _logger.LogInformation(JsonSerializer.Serialize(revisions));
            }
            catch (Exception e)
            {
                _logger.LogError("exception: {}", e);
            }
        }
    }
}
