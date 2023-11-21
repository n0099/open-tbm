using Dapper;

namespace tbm.Crawler.Worker;

public class PushAllPostContentsIntoSonicWorker(
        ILogger<PushAllPostContentsIntoSonicWorker> logger,
        IHostApplicationLifetime applicationLifetime,
        IConfiguration config,
        ILifetimeScope scope0,
        SonicPusher pusher)
    : ErrorableWorker(logger, applicationLifetime, shouldExitOnException: true, shouldExitOnFinish: true)
{
    private readonly IConfiguration _config = config.GetSection("Sonic");

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await using var scope1 = scope0.BeginLifetimeScope();
        var db = scope1.Resolve<CrawlerDbContext.NewDefault>()();
#pragma warning disable IDISP004 // Don't ignore created IDisposable
        var forumPostCountsTuples = db.Database.GetDbConnection()
#pragma warning restore IDISP004 // Don't ignore created IDisposable
            .Query<(Fid Fid, int ReplyCount, int SubReplyCount)>(
                string.Join(" UNION ALL ", (from f in db.Forums select f.Fid).AsEnumerable().Select(fid =>
                    $"SELECT {fid} AS Fid,"
                    + $"IFNULL((SELECT id FROM tbmc_f{fid}_reply ORDER BY id DESC LIMIT 1), 0) AS ReplyCount,"
                    + $"IFNULL((SELECT id FROM tbmc_f{fid}_subReply ORDER BY id DESC LIMIT 1), 0) AS SubReplyCount")))
            .ToList();
        var forumCount = forumPostCountsTuples.Count * 2; // reply and sub reply
        var totalPostCount = forumPostCountsTuples.Sum(t => t.ReplyCount)
                             + forumPostCountsTuples.Sum(t => t.SubReplyCount);
        var pushedPostCount = 0;
        foreach (var (index, (fid, replyCount, subReplyCount)) in forumPostCountsTuples.Index())
        {
            var forumIndex = (index + 1) * 2; // counting from one, including both reply and sub reply
            var dbWithFid = scope1.Resolve<CrawlerDbContext.New>()(fid);

            // enlarge the default mysql connection read/write timeout to prevent it close connection while pushing
            // since pushing post contents into sonic is slower than fetching records from mysql, aka back-pressure
            _ = await dbWithFid.Database.ExecuteSqlRawAsync(
                "SET SESSION net_read_timeout = 3600; SET SESSION net_write_timeout = 3600;", stoppingToken);

            _ = await pusher.Ingest.FlushBucketAsync($"{pusher.CollectionPrefix}replies_content", $"f{fid}");
            pushedPostCount += PushPostContentsWithTiming(fid, forumIndex - 1, forumCount, "replies",
                replyCount, totalPostCount, pushedPostCount, dbWithFid.ReplyContents.AsNoTracking(),
                r => pusher.PushPost(fid, "replies", r.Pid, Helper.ParseThenUnwrapPostContent(r.ProtoBufBytes)), stoppingToken);
            await TriggerConsolidate();

            _ = await pusher.Ingest.FlushBucketAsync($"{pusher.CollectionPrefix}subReplies_content", $"f{fid}");
            pushedPostCount += PushPostContentsWithTiming(fid, forumIndex, forumCount, "sub replies",
                subReplyCount, totalPostCount, pushedPostCount, dbWithFid.SubReplyContents.AsNoTracking(),
                sr => pusher.PushPost(fid, "subReplies", sr.Spid, Helper.ParseThenUnwrapPostContent(sr.ProtoBufBytes)), stoppingToken);
            await TriggerConsolidate();

            async Task TriggerConsolidate()
            {
                using var control = NSonicFactory.Control(
                    _config.GetValue("Hostname", "localhost"),
                    _config.GetValue("Port", 1491),
                    _config.GetValue("Secret", "SecretPassword"));
                await control.TriggerAsync("consolidate");
            }
        }
    }

    private int PushPostContentsWithTiming<T>(
        Fid fid, int currentForumIndex, int forumCount, string postTypeInLog,
        int postApproxCount,
        int forumsPostTotalApproxCount,
        int previousPushedPostCount,
        IEnumerable<T> postContents,
        Func<T, float> pushDelegate,
        CancellationToken stoppingToken = default)
    {
        var stopwatch = new Stopwatch();
        stopwatch.Start();
        logger.LogInformation("Pushing all historical {}' content into sonic for fid {} started", postTypeInLog, fid);
        var (pushedPostCount, durationCa) = postContents.Aggregate((Count: 0, DurationCa: 0f), (acc, post) =>
        {
            stoppingToken.ThrowIfCancellationRequested();
            var elapsedMs = pushDelegate(post);
            var pushedCount = acc.Count + 1;
            var totalPushedCount = previousPushedPostCount + pushedCount;
            var ca = ArchiveCrawlWorker.GetCumulativeAverage(elapsedMs, acc.DurationCa, pushedCount);
            if (pushedCount % 1000 == 0)
            {
                static double GetPercentage(float current, float total, int digits = 2) => Math.Round(current / total * 100, digits);
#pragma warning disable IDE0042 // Deconstruct variable declaration
                var currentForumEta = ArchiveCrawlWorker.GetEta(postApproxCount, pushedCount, ca);
                var totalForumEta = ArchiveCrawlWorker.GetEta(forumsPostTotalApproxCount, totalPushedCount, ca);
#pragma warning restore IDE0042 // Deconstruct variable declaration
                logger.LogInformation("Pushing progress for {} in fid {}: {}/~{} ({}%) cumulativeAvg={:F3}ms"
                                      + " ETA: {} @ {}, Total forums progress: {}/{} posts: {}/~{} ({}%) ETA {} @ {}",
                    postTypeInLog, fid,
                    pushedCount, postApproxCount, GetPercentage(pushedCount, postApproxCount),
                    ca, currentForumEta.Relative, currentForumEta.At, currentForumIndex, forumCount,
                    totalPushedCount, forumsPostTotalApproxCount, GetPercentage(totalPushedCount, forumsPostTotalApproxCount),
                    totalForumEta.Relative, totalForumEta.At);
                Console.Title = $"Pushing progress for {postTypeInLog} in fid {fid}"
                                + $": {pushedCount}/~{postApproxCount} ({GetPercentage(pushedCount, postApproxCount)}%)"
                                + $", Total forums progress: {currentForumIndex}/{forumCount} posts:"
                                + $" {totalPushedCount}/~{forumsPostTotalApproxCount} ({GetPercentage(totalPushedCount, forumsPostTotalApproxCount)}%)"
                                + $" ETA {totalForumEta.Relative} @ {totalForumEta.At}";
            }
            return (pushedCount, ca);
        });
        logger.LogInformation("Pushing {} historical {}' content into sonic for fid {} finished after {} (total={}ms, cumulativeAvg={:F3}ms)",
            pushedPostCount, postTypeInLog, fid, stopwatch.Elapsed.Humanize(precision: 5, minUnit: TimeUnit.Second), stopwatch.ElapsedMilliseconds, durationCa);
        return pushedPostCount;
    }
}
