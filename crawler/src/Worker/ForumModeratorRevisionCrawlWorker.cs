using AngleSharp;
using IConfiguration = Microsoft.Extensions.Configuration.IConfiguration;

namespace tbm.Crawler.Worker
{
    public class ForumModeratorRevisionCrawlWorker : CyclicCrawlWorker
    {
        private readonly ILogger<MainCrawlWorker> _logger;
        private readonly ILifetimeScope _scope0;

        public ForumModeratorRevisionCrawlWorker(ILogger<MainCrawlWorker> logger,
            IConfiguration config, ILifetimeScope scope0) : base(logger, config)
        {
            _logger = logger;
            _scope0 = scope0;
        }

        protected override async Task DoWork(CancellationToken stoppingToken)
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<TbmDbContext.New>()(0);
            var browsing = BrowsingContext.New(Configuration.Default.WithDefaultLoader());
            foreach (var forum in from f in db.Forum where f.IsCrawling select new {f.Fid, f.Name})
            {
                if (stoppingToken.IsCancellationRequested) return;
                var doc = await browsing.OpenAsync($"https://tieba.baidu.com/bawu2/platform/listBawuTeamInfo?ie=utf-8&word={forum.Name}", stoppingToken);
                var moderators = doc.QuerySelectorAll("div.bawu_single_type").Select(typeEl =>
                {
                    var type = typeEl.QuerySelector("div.title")?.Children
                        .Select(el => el.ClassList)
                        .First(classNames => classNames.Any(className => className.EndsWith("_icon")))
                        .Select(className => className.Split("_")[0])
                        .First(className => !string.IsNullOrWhiteSpace(className));
                    if (string.IsNullOrEmpty(type)) throw new TiebaException();
                    var memberPortraits = typeEl.QuerySelectorAll(".member")
                        .Select(memberEl => memberEl.QuerySelector("a.avatar")
                            ?.GetAttribute("href")?.Split("/home/main?id=")[1].NullIfWhiteSpace())
                        .OfType<string>();
                    return memberPortraits.Select(portrait => (type, portrait));
                });
            }
        }
    }
}
