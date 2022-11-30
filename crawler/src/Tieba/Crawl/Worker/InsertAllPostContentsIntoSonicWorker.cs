using Humanizer;
using NSonic;

namespace tbm.Crawler
{
    public class InsertAllPostContentsIntoSonicWorker : BackgroundService
    {
        public class SonicPusher
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

            public void PushPost(Fid fid, string postType, PostId postId, byte[]? postContent)
            {
                if (postContent == null) return;
                var content = PostContentWrapper.Parser.ParseFrom(postContent).Value.Aggregate("", (acc, content) => acc + content.Text).Trim();
                if (content == "") return;
                content = content.Replace("\\", "\\\\").Replace("\n", "\\n").Replace("\"", "\\\""); // https://github.com/spikensbror-dotnet/nsonic/pull/10
                try
                {
                    foreach (var text in content.Chunk(10000)) // https://github.com/spikensbror-dotnet/nsonic/issues/11
                        Ingest.Push($"{CollectionPrefix}f{fid}", postType, postId.ToString(), text.ToString(), "cmn");
                }
                catch (Exception e)
                {
                    _logger.LogError(e, "Error while pushing the content of post id {} for {} in fid {} into sonic, content={}", postId, postType, fid, content);
                }
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
            foreach (var fid in from f in scope1.Resolve<TbmDbContext.New>()(0).ForumsInfo select f.Fid)
            {
                var dbWithFid = scope1.Resolve<TbmDbContext.New>()(fid);
                _ = await dbWithFid.Database.ExecuteSqlRawAsync(
                    "SET SESSION net_read_timeout = 3600; SET SESSION net_write_timeout = 3600;", stoppingToken);
                _logger.LogInformation("Flushed {} {}' content of fid {} in sonic",
                    _pusher.Ingest.FlushBucket($"{_pusher.CollectionPrefix}f{fid}", "replies"), "replies", fid);
                PushPostContents(fid, "replies", dbWithFid.ReplyContents.Count(), dbWithFid.ReplyContents.AsNoTracking(),
                    r => _pusher.PushPost(fid, "replies", r.Pid, r.Content));

                _logger.LogInformation("Flushed {} {}' content of fid {} in sonic",
                    _pusher.Ingest.FlushBucket($"{_pusher.CollectionPrefix}f{fid}", "subReplies"), "sub replies", fid);
                PushPostContents(fid, "sub replies", dbWithFid.SubReplyContents.Count(), dbWithFid.SubReplyContents.AsNoTracking(),
                    sr => _pusher.PushPost(fid, "subReplies", sr.Spid, sr.Content));
            }
            NSonicFactory.Control(
                _config.GetValue("Hostname", "localhost"),
                _config.GetValue("Port", 1491),
                _config.GetValue("Secret", "SecretPassword")
            ).Trigger("consolidate");
        }

        private void PushPostContents<T>(uint fid, string postTypeInLog, int postsTotalCount, IEnumerable<T> postContents, Action<T> pushCallback)
        {
            var stopWatch = new Stopwatch();
            var stopWatchPushing = new Stopwatch();
            _logger.LogInformation("Pushing all historical {}' content into sonic for fid {} started", postTypeInLog, fid);
            stopWatch.Start();
            var pushedStats = postContents.Aggregate((Count: 0, CumulativeAvg: 0f), (acc, post) =>
            {
                stopWatchPushing.Restart();
                pushCallback(post);
                var elapsedMs = stopWatchPushing.ElapsedMilliseconds;
                if (elapsedMs > 500) _logger.LogWarning("Pushing a single {}' content into sonic for fid {} spending {}ms", postTypeInLog, fid, elapsedMs);
                var finishedCount = acc.Count + 1;
                var ca = ArchiveCrawlWorker.CalcCumulativeAverage(elapsedMs, acc.CumulativeAvg, finishedCount);
                if (finishedCount % 1000 == 0)
                {
                    var etaDateTime = DateTime.Now.Add(TimeSpan.FromMilliseconds((postsTotalCount - finishedCount) * ca));
                    var etaRelative = etaDateTime.Humanize();
                    var etaAt = etaDateTime.ToString("MM-dd HH:mm:ss");
                    _logger.LogInformation("Pushing progress: {}/{} cumulativeAvg={:F2}ms ETA={} {}",
                        finishedCount, postsTotalCount, ca, etaRelative, etaAt);
                    Console.Title = $"Pushing progress: {finishedCount}/{postsTotalCount} ETA: {etaRelative} {etaAt}";
                }
                return (finishedCount, ca); // harmonic mean
            });
            _logger.LogInformation("Pushing {} historical {}' content into sonic for fid {} finished after {} (total={}ms, cumulativeAvg={}ms)",
                pushedStats.Count, postTypeInLog, fid, stopWatch.Elapsed.Humanize(), stopWatch.ElapsedMilliseconds, pushedStats.CumulativeAvg);
        }
    }
}
