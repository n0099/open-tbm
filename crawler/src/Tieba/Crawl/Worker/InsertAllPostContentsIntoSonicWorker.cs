using Humanizer;
using Humanizer.Localisation;
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
            void TriggerConsolidate() => NSonicFactory.Control(
                _config.GetValue("Hostname", "localhost"),
                _config.GetValue("Port", 1491),
                _config.GetValue("Secret", "SecretPassword")
            ).Trigger("consolidate");
            await using var scope1 = _scope0.BeginLifetimeScope();
            var fids = (from f in scope1.Resolve<TbmDbContext.New>()(0).ForumsInfo select f.Fid).ToList();
            var fidsCount = fids.Count * 2; // reply and sub reply
            foreach (var (fid, index) in fids.WithIndex())
            {
                var dbWithFid = scope1.Resolve<TbmDbContext.New>()(fid);
                _ = await dbWithFid.Database.ExecuteSqlRawAsync(
                    // enlarge the default mysql connection read/write timeout to prevent it close connection while pushing
                    // since pushing post contents into sonic is slower than fetching records from mysql, aka back-pressure
                    "SET SESSION net_read_timeout = 3600; SET SESSION net_write_timeout = 3600;", stoppingToken);

                _ = _pusher.Ingest.FlushBucket($"{_pusher.CollectionPrefix}replies_content", $"f{fid}");
                PushPostContents(fid, index, fidsCount, "replies", dbWithFid.ReplyContents.Count(), dbWithFid.ReplyContents.AsNoTracking(),
                    r => _pusher.PushPost(fid, "replies", r.Pid, r.Content), stoppingToken);
                TriggerConsolidate();

                _ = _pusher.Ingest.FlushBucket($"{_pusher.CollectionPrefix}subReplies_content", $"f{fid}");
                PushPostContents(fid, index + 1, fidsCount, "sub replies", dbWithFid.SubReplyContents.Count(), dbWithFid.SubReplyContents.AsNoTracking(),
                    sr => _pusher.PushPost(fid, "subReplies", sr.Spid, sr.Content), stoppingToken);
                TriggerConsolidate();
            }
        }

        private void PushPostContents<T>(Fid fid,
            int currentFidIndex,
            int fidTotalCount,
            string postTypeInLog,
            int postsTotalCount,
            IEnumerable<T> postContents,
            Func<T, float> pushCallback,
            CancellationToken stoppingToken)
        {
            var stopWatch = new Stopwatch();
            stopWatch.Start();
            _logger.LogInformation("Pushing all historical {}' content into sonic for fid {} started", postTypeInLog, fid);
            var (count, cumulativeAvg) = postContents.Aggregate((Count: 0, CumulativeAvg: 0f), (acc, post) =>
            {
                stoppingToken.ThrowIfCancellationRequested();
                var elapsedMs = pushCallback(post);
                var finishedCount = acc.Count + 1;
                var ca = ArchiveCrawlWorker.CalcCumulativeAverage(elapsedMs, acc.CumulativeAvg, finishedCount);
                if (finishedCount % 1000 == 0)
                {
                    var etaTimeSpan = TimeSpan.FromMilliseconds((postsTotalCount - finishedCount) * ca);
                    var etaRelative = etaTimeSpan.Humanize(precision: 5, minUnit: TimeUnit.Second);
                    var etaAt = DateTime.Now.Add(etaTimeSpan).ToString("MM-dd HH:mm:ss");
                    _logger.LogInformation("Pushing progress for {} in fid {}: {}/{} cumulativeAvg={:F3}ms ETA: {} @ {}. Total fids progress: {}/{}",
                        postTypeInLog, fid, finishedCount, postsTotalCount, ca, etaRelative, etaAt, currentFidIndex, fidTotalCount);
                    Console.Title = $"Pushing progress for {postTypeInLog} in fid {fid}: {finishedCount}/{postsTotalCount} ETA: {etaRelative} @ {etaAt}. Total fids progress: {currentFidIndex}/{fidTotalCount}";
                }
                return (finishedCount, ca);
            });
            _logger.LogInformation("Pushing {} historical {}' content into sonic for fid {} finished after {} (total={}ms, cumulativeAvg={:F3}ms)",
                count, postTypeInLog, fid, stopWatch.Elapsed.Humanize(precision: 5, minUnit: TimeUnit.Second), stopWatch.ElapsedMilliseconds, cumulativeAvg);
        }
    }
}
