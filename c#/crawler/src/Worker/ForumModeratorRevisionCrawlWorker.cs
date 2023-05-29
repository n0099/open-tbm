using AngleSharp;
using AngleSharp.Io;
using LinqToDB;
using LinqToDB.EntityFrameworkCore;
using IConfiguration = Microsoft.Extensions.Configuration.IConfiguration;

namespace tbm.Crawler.Worker;

public class ForumModeratorRevisionCrawlWorker : CyclicCrawlWorker
{
    private readonly ILifetimeScope _scope0;
    private readonly IConfiguration _config;

    public ForumModeratorRevisionCrawlWorker(
        ILogger<ForumModeratorRevisionCrawlWorker> logger,
        IHostApplicationLifetime applicationLifetime,
        IConfiguration config,
        ILifetimeScope scope0
    ) : base(logger, applicationLifetime, config, shouldRunAtFirst: false) =>
        (_config, _scope0) = (config.GetSection("CrawlForumModeratorRevision"), scope0);

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await using var scope1 = _scope0.BeginLifetimeScope();
        var db0 = scope1.Resolve<CrawlerDbContext.New>()(0);
        foreach (var forum in from f in db0.Forums.AsNoTracking() where f.IsCrawling select new {f.Fid, f.Name})
        {
            if (stoppingToken.IsCancellationRequested) return;
            var requester = new DefaultHttpRequester {Headers =
            {
                {"User-Agent", _config.GetValue("UserAgent", Strings.DefaultUserAgent) ?? Strings.DefaultUserAgent},
                {"Referrer", $"https://tieba.baidu.com/bawu2/platform/detailsInfo?word={forum.Name}&ie=utf-8"}
            }};
            var browsing = BrowsingContext.New(Configuration.Default.With(requester).WithDefaultLoader());
            var url = $"https://tieba.baidu.com/bawu2/platform/listBawuTeamInfo?ie=utf-8&word={forum.Name}";
            var doc = await browsing.OpenAsync(url, stoppingToken);

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
                        ?.GetAttribute("href")?.Split("/home/main?id=")[1].NullIfEmpty())
                    .OfType<string>();
                return memberPortraits.Select(portrait => (type, portrait));
            });

            var fid = forum.Fid;
            Helper.GetNowTimestamp(out var now);
            var db1 = scope1.Resolve<CrawlerDbContext.New>()(0);
            await using var transaction = await db1.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);
            var revisions = moderators
                .SelectMany(i => i)
                .GroupBy(t => t.portrait)
                .Select(g => new ForumModeratorRevision
                {
                    DiscoveredAt = now,
                    Fid = fid,
                    Portrait = g.Key,
                    // user can serve as multiple moderators, so join these types with commas
                    // the https://en.wikipedia.org/wiki/Order_of_precedence is same with div.bawu_single_type in the response HTML
                    ModeratorType = string.Join(',', g.Select(t => t.type))
                }).ToList();
            var existingLatestRevisions = (
                from rev in db1.ForumModeratorRevisions.AsNoTracking()
                where rev.Fid == fid
                select new
                {
                    rev.Portrait,
                    rev.ModeratorType,
                    Rank = Sql.Ext.Rank().Over().PartitionBy(rev.Portrait).OrderByDesc(rev.DiscoveredAt).ToValue()
                }).Where(e => e.Rank == 1)
                .ToLinqToDB().ToList();

            db1.ForumModeratorRevisions.AddRange(revisions.ExceptBy(
                existingLatestRevisions.Select(e => (e.Portrait, e.ModeratorType)),
                rev => (rev.Portrait, rev.ModeratorType)));
            db1.ForumModeratorRevisions.AddRange(existingLatestRevisions
                .Where(e => e.ModeratorType != "") // filter out revisions that recorded someone who resigned from moderators
                .ExceptBy(revisions.Select(rev => rev.Portrait), e => e.Portrait)
                .Select(e => new ForumModeratorRevision
                {
                    DiscoveredAt = now,
                    Fid = fid,
                    Portrait = e.Portrait,
                    ModeratorType = "" // moderator only exists in DB means he is no longer a moderator
                }));
            _ = await db1.SaveChangesAsync(stoppingToken);
            await transaction.CommitAsync(stoppingToken);
        }
    }
}
