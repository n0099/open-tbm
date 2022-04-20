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
                var crawler = scope.Resolve<ReplyCrawlFacade.New>()(0, 0);
                (await crawler.CrawlPageRange(1)).SavePosts<ReplyRevision>(
                    out var existingOrNewLookupOnOldPosts,
                    out var existingOrNewLookupOnOldUsers,
                    out var postRevisions);
                var existingOrNewLookupOnOldPostsId = existingOrNewLookupOnOldPosts.Select(g => new {existing = g.Key, id = g.Select(p => p.Pid)});
                var existingOrNewLookupOnOldUsersId = existingOrNewLookupOnOldUsers.Select(g => new {existing = g.Key, id = g.Select(u => u.Uid)});
                _logger.LogInformation("existingOrNewLookupOnOldPostsId: {}", JsonSerializer.Serialize(existingOrNewLookupOnOldPostsId));
                _logger.LogInformation("existingOrNewLookupOnOldUsersId: {}", JsonSerializer.Serialize(existingOrNewLookupOnOldUsersId));
                _logger.LogInformation("postRevisions: {}", JsonSerializer.Serialize(postRevisions));
            }
            catch (Exception e)
            {
                _logger.LogError("exception: {}", e);
            }
        }
    }
}
