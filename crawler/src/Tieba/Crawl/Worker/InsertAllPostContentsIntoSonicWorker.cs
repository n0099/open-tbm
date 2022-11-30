using Humanizer;
using NSonic;

namespace tbm.Crawler
{
    public class InsertAllPostContentsIntoSonicWorker : BackgroundService
    {
        public class SonicPusher
        {
            private readonly ILogger<SonicPusher> _logger;
            private readonly ISonicIngestConnection _ingest;
            private readonly string _bucket;

            public SonicPusher(ILogger<SonicPusher> logger, IConfiguration config)
            {
                _logger = logger;
                config = config.GetSection("Sonic");
                _ingest = NSonicFactory.Ingest(
                    config.GetValue("Hostname", "localhost"),
                    config.GetValue("Port", 1491),
                    config.GetValue("Secret", "SecretPassword")
                );
                _bucket = config.GetValue("Bucket", "tbm") ?? "tbm";
            }

            public void PushPost(Fid fid, string postType, PostId postId, byte[]? postContent)
            {
                if (postContent == null) return;
                var content = PostContentWrapper.Parser.ParseFrom(postContent).Value.Aggregate("", (acc, content) => acc + content.Text).Trim();
                if (content == "") return;
                content = content.Replace("\n", "\\n").Replace("\"", "\\\""); // https://github.com/spikensbror-dotnet/nsonic/pull/10
                try
                {
                    _ingest.Push($"f{fid}_{postType}", _bucket, postId.ToString(), content, "cmn");
                }
                catch (Exception e)
                {
                    _logger.LogError(e, "Error while pushing the content of {} for {} in fid {} into sonic, content={}", postId, postType, fid, content);
                }
            }
        }

        private readonly ILogger<InsertAllPostContentsIntoSonicWorker> _logger;
        private readonly ILifetimeScope _scope0;
        private readonly SonicPusher _pusher;

        public InsertAllPostContentsIntoSonicWorker(ILogger<InsertAllPostContentsIntoSonicWorker> logger, ILifetimeScope scope0, SonicPusher pusher)
        {
            _logger = logger;
            _scope0 = scope0;
            _pusher = pusher;
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            foreach (var fid in from f in scope1.Resolve<TbmDbContext.New>()(0).ForumsInfo select f.Fid)
            {
                var dbWithFid = scope1.Resolve<TbmDbContext.New>()(fid);
                PushPostContents(fid, "replies", dbWithFid.ReplyContents, r => _pusher.PushPost(fid, "replies", r.Pid, r.Content));
                PushPostContents(fid, "sub replies", dbWithFid.SubReplyContents, sr => _pusher.PushPost(fid, "replies", sr.Spid, sr.Content));
            }
        }

        private void PushPostContents<T>(uint fid, string postTypeInLog, IEnumerable<T> postContents, Action<T> pushCallback)
        {
            var stopWatch = new Stopwatch();
            var stopWatchPushing = new Stopwatch();
            _logger.LogInformation("Pushing all historical {} content for fid {} started", postTypeInLog, fid);
            stopWatch.Start();
            var pushedStats = postContents.Aggregate((Count: 0, AvgTime: 0f), (acc, post) =>
            {
                stopWatchPushing.Restart();
                pushCallback(post);
                return (acc.Count + 1, acc.AvgTime + (1f / stopWatchPushing.ElapsedMilliseconds)); // harmonic mean
            });
            pushedStats.AvgTime /= pushedStats.Count;
            _logger.LogInformation("Pushing {} historical {} content for fid {} finished after {} ({}ms)",
                pushedStats.Count, postTypeInLog, fid, stopWatch.Elapsed.Humanize(), stopWatch.ElapsedMilliseconds);
        }
    }
}
