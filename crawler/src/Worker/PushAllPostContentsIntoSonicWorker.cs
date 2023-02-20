using Dapper;
using Humanizer;
using Humanizer.Localisation;
using NSonic;

namespace tbm.Crawler.Worker;

public class PushAllPostContentsIntoSonicWorker : ErrorableWorker
{
    private readonly ILogger<PushAllPostContentsIntoSonicWorker> _logger;
    private readonly IConfiguration _config;
    private readonly ILifetimeScope _scope0;
    private readonly SonicPusher _pusher;

    public PushAllPostContentsIntoSonicWorker(ILogger<PushAllPostContentsIntoSonicWorker> logger,
        IConfiguration config, ILifetimeScope scope0, SonicPusher pusher) : base(logger)
    {
        _logger = logger;
        _config = config.GetSection("Sonic");
        _scope0 = scope0;
        _pusher = pusher;
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await using var scope1 = _scope0.BeginLifetimeScope();
        var db = scope1.Resolve<TbmDbContext.New>()(0);
        var forumPostCountsTuples = db.Database.GetDbConnection()
            .Query<(Fid Fid, int ReplyCount, int SubReplyCount)>(
                string.Join(" UNION ALL ", (from f in db.Forum select f.Fid).ToList().Select(fid =>
                    $"SELECT {fid} AS Fid,"
                    + $"IFNULL((SELECT id FROM tbmc_f{fid}_reply ORDER BY id DESC LIMIT 1), 0) AS ReplyCount,"
                    + $"IFNULL((SELECT id FROM tbmc_f{fid}_subReply ORDER BY id DESC LIMIT 1), 0) AS SubReplyCount"))
            ).ToList();
        var forumCount = forumPostCountsTuples.Count * 2; // reply and sub reply
        var totalPostCount = forumPostCountsTuples.Sum(t => t.ReplyCount)
                             + forumPostCountsTuples.Sum(t => t.SubReplyCount);
        var pushedPostCount = 0;
        foreach (var ((fid, replyCount, subReplyCount), index) in forumPostCountsTuples.WithIndex())
        {
            var forumIndex = (index + 1) * 2; // counting from one, including both reply and sub reply
            var dbWithFid = scope1.Resolve<TbmDbContext.New>()(fid);
            _ = await dbWithFid.Database.ExecuteSqlRawAsync(
                // enlarge the default mysql connection read/write timeout to prevent it close connection while pushing
                // since pushing post contents into sonic is slower than fetching records from mysql, aka back-pressure
                "SET SESSION net_read_timeout = 3600; SET SESSION net_write_timeout = 3600;", stoppingToken);

            _ = _pusher.Ingest.FlushBucket($"{_pusher.CollectionPrefix}replies_content", $"f{fid}");
            pushedPostCount += PushPostContentsWithTiming(fid, forumIndex - 1, forumCount, "replies",
                replyCount, totalPostCount, pushedPostCount, dbWithFid.ReplyContents.AsNoTracking(),
                r => _pusher.PushPost(fid, "replies", r.Pid, Helper.ParseThenUnwrapPostContent(r.Content)), stoppingToken);
            TriggerConsolidate();

            _ = _pusher.Ingest.FlushBucket($"{_pusher.CollectionPrefix}subReplies_content", $"f{fid}");
            pushedPostCount += PushPostContentsWithTiming(fid, forumIndex, forumCount, "sub replies",
                subReplyCount, totalPostCount, pushedPostCount, dbWithFid.SubReplyContents.AsNoTracking(),
                sr => _pusher.PushPost(fid, "subReplies", sr.Spid, Helper.ParseThenUnwrapPostContent(sr.Content)), stoppingToken);
            TriggerConsolidate();

            void TriggerConsolidate() => NSonicFactory.Control(
                _config.GetValue("Hostname", "localhost"),
                _config.GetValue("Port", 1491),
                _config.GetValue("Secret", "SecretPassword")
            ).Trigger("consolidate");
        }
    }

    private int PushPostContentsWithTiming<T>(Fid fid,
        int currentForumIndex,
        int forumCount,
        string postTypeInLog,
        int postApproxCount,
        int forumsPostTotalApproxCount,
        int previousPushedPostCount,
        IEnumerable<T> postContents,
        Func<T, float> pushCallback,
        CancellationToken stoppingToken)
    {
        var stopWatch = new Stopwatch();
        stopWatch.Start();
        _logger.LogInformation("Pushing all historical {}' content into sonic for fid {} started", postTypeInLog, fid);
        var (pushedPostCount, durationCa) = postContents.Aggregate((Count: 0, DurationCa: 0f), (acc, post) =>
        {
            stoppingToken.ThrowIfCancellationRequested();
            var elapsedMs = pushCallback(post);
            var pushedCount = acc.Count + 1;
            var totalPushedCount = previousPushedPostCount + pushedCount;
            var ca = ArchiveCrawlWorker.CalcCumulativeAverage(elapsedMs, acc.DurationCa, pushedCount);
            if (pushedCount % 1000 == 0)
            {
                static double CalcPercentage(float current, float total, int digits = 2) => Math.Round(current / total * 100, digits);
                var currentForumEta = ArchiveCrawlWorker.CalcEta(postApproxCount, pushedCount, ca);
                var totalForumEta = ArchiveCrawlWorker.CalcEta(forumsPostTotalApproxCount, totalPushedCount, ca);
                _logger.LogInformation("Pushing progress for {} in fid {}: {}/~{} ({}%) cumulativeAvg={:F3}ms"
                                       + " ETA: {} @ {}, Total forums progress: {}/{} posts: {}/~{} ({}%) ETA {} @ {}",
                    postTypeInLog, fid,
                    pushedCount, postApproxCount, CalcPercentage(pushedCount, postApproxCount),
                    ca, currentForumEta.Relative, currentForumEta.At, currentForumIndex, forumCount,
                    totalPushedCount, forumsPostTotalApproxCount, CalcPercentage(totalPushedCount, forumsPostTotalApproxCount),
                    totalForumEta.Relative, totalForumEta.At);
                Console.Title = $"Pushing progress for {postTypeInLog} in fid {fid}"
                                + $": {pushedCount}/~{postApproxCount} ({CalcPercentage(pushedCount, postApproxCount)}%)"
                                + $", Total forums progress: {currentForumIndex}/{forumCount} posts:"
                                + $" {totalPushedCount}/~{forumsPostTotalApproxCount} ({CalcPercentage(totalPushedCount, forumsPostTotalApproxCount)}%)"
                                + $" ETA {totalForumEta.Relative} @ {totalForumEta.At}";
            }
            return (pushedCount, ca);
        });
        _logger.LogInformation("Pushing {} historical {}' content into sonic for fid {} finished after {} (total={}ms, cumulativeAvg={:F3}ms)",
            pushedPostCount, postTypeInLog, fid, stopWatch.Elapsed.Humanize(precision: 5, minUnit: TimeUnit.Second), stopWatch.ElapsedMilliseconds, durationCa);
        return pushedPostCount;
    }
}
