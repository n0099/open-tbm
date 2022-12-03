using Dapper;
using Humanizer;
using Humanizer.Localisation;
using NSonic;

namespace tbm.Crawler
{
    public class InsertAllPostContentsIntoSonicWorker : BackgroundService
    {
        public sealed class SonicPusher : IDisposable
        {
            public ISonicIngestConnection Ingest { get; }
            public string CollectionPrefix { get; }
            private readonly ILogger<SonicPusher> _logger;

            public SonicPusher(ILogger<SonicPusher> logger, IConfiguration config)
            {
                _logger = logger;
                config = config.GetSection("Sonic");
                Ingest = NSonicFactory.Ingest(
                    config.GetValue("Hostname", "localhost"),
                    config.GetValue("Port", 1491),
                    config.GetValue("Secret", "SecretPassword")
                );
                CollectionPrefix = config.GetValue<string>("CollectionPrefix") ?? "tbm_";
            }

            public void Dispose() => Ingest.Dispose();

            public float PushPost(Fid fid, string postType, PostId postId, byte[]? postContent)
            {
                var stopWatch = new Stopwatch();
                stopWatch.Start();
                float GetElapsedMs() => (float)stopWatch.ElapsedTicks / Stopwatch.Frequency * 1000;

                if (postContent == null) return GetElapsedMs();
                var content = PostContentWrapper.Parser.ParseFrom(postContent).Value.Where(c => c.Type != 2) // filter out emoticons alt text
                    .Aggregate("", (acc, content) => $"{acc} {content.Text}").Trim();
                if (content == "") return GetElapsedMs();
                content = content.Replace("\\", "\\\\").Replace("\n", "\\n").Replace("\"", "\\\""); // https://github.com/spikensbror-dotnet/nsonic/pull/10

                try
                {
                    foreach (var text in content.Chunk(30000)) // https://github.com/spikensbror-dotnet/nsonic/issues/11
                        Ingest.Push($"{CollectionPrefix}{postType}_content", $"f{fid}", postId.ToString(), text.ToString(), "cmn");
                }
                catch (Exception e)
                {
                    _logger.LogError(e, "Error while pushing the content of post id {} for {} in fid {} into sonic, content={}",
                        postId, postType, fid, content);
                }

                var ret = GetElapsedMs();
                if (ret > 1000)
                    _logger.LogWarning("Pushing a single post content with length {} into sonic for {} in fid {} spending {:F0}ms, content={}",
                        content.Length, postType, fid, ret, content);
                return ret;
            }
        }

        private readonly ILogger<InsertAllPostContentsIntoSonicWorker> _logger;
        private readonly IConfiguration _config;
        private readonly ILifetimeScope _scope0;
        private readonly SonicPusher _pusher;

        public InsertAllPostContentsIntoSonicWorker(ILogger<InsertAllPostContentsIntoSonicWorker> logger,
            IConfiguration config, ILifetimeScope scope0, SonicPusher pusher)
        {
            _logger = logger;
            _config = config.GetSection("Sonic");
            _scope0 = scope0;
            _pusher = pusher;
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<TbmDbContext.New>()(0);
            var forumsAndPostsCount = db.Database.GetDbConnection().Query<(Fid Fid, int RepliesCount, int SubRepliesCount)>(
                string.Join(" UNION ALL ", (from f in db.ForumsInfo select f.Fid).ToList().Select(fid =>
                    $"SELECT {fid} AS Fid,"
                    + $"IFNULL((SELECT id FROM tbm_f{fid}_replies ORDER BY id DESC LIMIT 1), 0) AS RepliesCount,"
                    + $"IFNULL((SELECT id FROM tbm_f{fid}_subReplies ORDER BY id DESC LIMIT 1), 0) AS SubRepliesCount"))).ToList();
            var forumsCount = forumsAndPostsCount.Count * 2; // reply and sub reply
            var totalPostsCount = forumsAndPostsCount.Sum(i => i.RepliesCount) + forumsAndPostsCount.Sum(i => i.SubRepliesCount);
            var pushedPostsCount = 0;
            foreach (var ((fid, repliesCount, subRepliesCount), index) in forumsAndPostsCount.WithIndex())
            {
                var forumIndex = (index + 1) * 2; // counting from one, including both reply and sub reply
                var dbWithFid = scope1.Resolve<TbmDbContext.New>()(fid);
                _ = await dbWithFid.Database.ExecuteSqlRawAsync(
                    // enlarge the default mysql connection read/write timeout to prevent it close connection while pushing
                    // since pushing post contents into sonic is slower than fetching records from mysql, aka back-pressure
                    "SET SESSION net_read_timeout = 3600; SET SESSION net_write_timeout = 3600;", stoppingToken);

                _ = _pusher.Ingest.FlushBucket($"{_pusher.CollectionPrefix}replies_content", $"f{fid}");
                pushedPostsCount += PushPostContentsTimingWrapper(fid, forumIndex - 1, forumsCount, "replies",
                    repliesCount, totalPostsCount, pushedPostsCount, dbWithFid.ReplyContents.AsNoTracking(),
                    r => _pusher.PushPost(fid, "replies", r.Pid, r.Content), stoppingToken);
                TriggerConsolidate();

                _ = _pusher.Ingest.FlushBucket($"{_pusher.CollectionPrefix}subReplies_content", $"f{fid}");
                pushedPostsCount += PushPostContentsTimingWrapper(fid, forumIndex, forumsCount, "sub replies",
                    subRepliesCount, totalPostsCount, pushedPostsCount, dbWithFid.SubReplyContents.AsNoTracking(),
                    sr => _pusher.PushPost(fid, "subReplies", sr.Spid, sr.Content), stoppingToken);
                TriggerConsolidate();

                void TriggerConsolidate() => NSonicFactory.Control(
                    _config.GetValue("Hostname", "localhost"),
                    _config.GetValue("Port", 1491),
                    _config.GetValue("Secret", "SecretPassword")
                ).Trigger("consolidate");
            }
        }

        private int PushPostContentsTimingWrapper<T>(Fid fid,
            int currentForumIndex,
            int forumsCount,
            string postTypeInLog,
            int postsApproxCount,
            int forumsPostsTotalApproxCount,
            int previousPushedPostsCount,
            IEnumerable<T> postContents,
            Func<T, float> pushCallback,
            CancellationToken stoppingToken)
        {
            var stopWatch = new Stopwatch();
            stopWatch.Start();
            _logger.LogInformation("Pushing all historical {}' content into sonic for fid {} started", postTypeInLog, fid);
            var (pushedPostsCount, durationCa) = postContents.Aggregate((Count: 0, DurationCa: 0f), (acc, post) =>
            {
                stoppingToken.ThrowIfCancellationRequested();
                var elapsedMs = pushCallback(post);
                var pushedCount = acc.Count + 1;
                var totalPushedCount = previousPushedPostsCount + pushedCount;
                var ca = ArchiveCrawlWorker.CalcCumulativeAverage(elapsedMs, acc.DurationCa, pushedCount);
                if (pushedCount % 1000 == 0)
                {
                    static float CalcPercentage(float current, float total, int digits = 2) => (float)Math.Round(current / total * 100, digits);
                    var currentForumEta = ArchiveCrawlWorker.CalcEta(postsApproxCount, pushedCount, ca);
                    var totalForumEta = ArchiveCrawlWorker.CalcEta(forumsPostsTotalApproxCount, totalPushedCount, ca);
                    _logger.LogInformation("Pushing progress for {} in fid {}: {}/~{} ({}%) cumulativeAvg={:F3}ms"
                                           + " ETA: {} @ {}, Total forums progress: {}/{} posts: {}/~{} ({}%) ETA {} @ {}",
                        postTypeInLog, fid,
                        pushedCount, postsApproxCount, CalcPercentage(pushedCount, postsApproxCount),
                        ca, currentForumEta.Relative, currentForumEta.At, currentForumIndex, forumsCount,
                        totalPushedCount, forumsPostsTotalApproxCount, CalcPercentage(totalPushedCount, forumsPostsTotalApproxCount),
                        totalForumEta.Relative, totalForumEta.At);
                    Console.Title = $"Pushing progress for {postTypeInLog} in fid {fid}"
                                    + $": {pushedCount}/~{postsApproxCount} ({CalcPercentage(pushedCount, postsApproxCount)}%)"
                                    + $", Total forums progress: {currentForumIndex}/{forumsCount} posts:"
                                    + $" {totalPushedCount}/~{forumsPostsTotalApproxCount} ({CalcPercentage(totalPushedCount, forumsPostsTotalApproxCount)}%)"
                                    + $" ETA {totalForumEta.Relative} @ {totalForumEta.At}";
                }
                return (pushedCount, ca);
            });
            _logger.LogInformation("Pushing {} historical {}' content into sonic for fid {} finished after {} (total={}ms, cumulativeAvg={:F3}ms)",
                pushedPostsCount, postTypeInLog, fid, stopWatch.Elapsed.Humanize(precision: 5, minUnit: TimeUnit.Second), stopWatch.ElapsedMilliseconds, durationCa);
            return pushedPostsCount;
        }
    }
}
