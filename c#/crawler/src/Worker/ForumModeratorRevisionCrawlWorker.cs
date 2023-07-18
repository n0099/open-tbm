using System.Web;
using AngleSharp;
using AngleSharp.Io;
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
        var db0 = scope1.Resolve<CrawlerDbContext.NewDefault>()();
        foreach (var forum in from e in db0.Forums.AsNoTracking() where e.IsCrawling select new {e.Fid, e.Name})
        {
            if (stoppingToken.IsCancellationRequested) return;
            await Save(forum.Fid, await Crawl(forum.Name, stoppingToken), stoppingToken);
        }
    }

    private async Task<IEnumerable<(string Type, string Portrait)>>
        Crawl(string forumName, CancellationToken stoppingToken = default)
    {
        var userAgent = _config.GetValue("UserAgent", Strings.DefaultUserAgent);
        var requester = new DefaultHttpRequester(userAgent) {Headers = {
            {"Referrer", $"https://tieba.baidu.com/bawu2/platform/detailsInfo?word={HttpUtility.UrlEncode(forumName)}&ie=utf-8"}
        }};
        var browsing = BrowsingContext.New(Configuration.Default.With(requester).WithDefaultLoader());
        var url = $"https://tieba.baidu.com/bawu2/platform/listBawuTeamInfo?ie=utf-8&word={forumName}";
        var doc = await browsing.OpenAsync(url, stoppingToken);

        return doc.QuerySelectorAll("div.bawu_single_type").Select(typeEl =>
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
        }).SelectMany(i => i);
    }

    private async Task Save(
        Fid fid,
        IEnumerable<(string Type, string Portrait)> moderators,
        CancellationToken stoppingToken = default)
    {
        await using var scope1 = _scope0.BeginLifetimeScope();
        var db = scope1.Resolve<CrawlerDbContext.NewDefault>()();
        await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);

        Helper.GetNowTimestamp(out var now);
        var revisions = moderators
            .GroupBy(t => t.Portrait)
            .Select(g => new ForumModeratorRevision
            {
                DiscoveredAt = now,
                Fid = fid,
                Portrait = g.Key,
                // user can serve as multiple moderators, so join these types with commas
                // the https://en.wikipedia.org/wiki/Order_of_precedence is same with div.bawu_single_type in the response HTML
                ModeratorTypes = string.Join(',', g.Select(t => t.Type))
            }).ToList();
        var existingLatestRevisions = (
                from rev in db.ForumModeratorRevisions.AsNoTracking()
                where rev.Fid == fid
                select new
                {
                    rev.Portrait,
                    rev.ModeratorTypes,
                    Rank = Sql.Ext.Rank().Over().PartitionBy(rev.Portrait).OrderByDesc(rev.DiscoveredAt).ToValue()
                }).Where(e => e.Rank == 1)
            .ToLinqToDB().ToList();

        db.ForumModeratorRevisions.AddRange(revisions.ExceptBy(
            existingLatestRevisions.Select(e => (e.Portrait, e.ModeratorTypes)),
            rev => (rev.Portrait, rev.ModeratorTypes)));
        db.ForumModeratorRevisions.AddRange(existingLatestRevisions
            .Where(e => e.ModeratorTypes != "") // filter out revisions that recorded someone who resigned from moderators
            .ExceptBy(revisions.Select(rev => rev.Portrait), e => e.Portrait)
            .Select(e => new ForumModeratorRevision
            {
                DiscoveredAt = now,
                Fid = fid,
                Portrait = e.Portrait,
                ModeratorTypes = "" // moderator only exists in DB means he is no longer a moderator
            }));

        _ = await db.SaveChangesAsync(stoppingToken);
        await transaction.CommitAsync(stoppingToken);
    }
}
