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

        private readonly ILifetimeScope _scope0;
        private readonly SonicPusher _pusher;

        public InsertAllPostContentsIntoSonicWorker(ILifetimeScope scope0, SonicPusher pusher)
        {
            _scope0 = scope0;
            _pusher = pusher;
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            foreach (var fid in from f in scope1.Resolve<TbmDbContext>().ForumsInfo select f.Fid)
            {
                var dbWithFid = scope1.Resolve<TbmDbContext.New>()(fid);
                dbWithFid.ReplyContents.ForEach(r => _pusher.PushPost(fid, "replies", r.Pid, r.Content));
                dbWithFid.SubReplyContents.ForEach(sr => _pusher.PushPost(fid, "subReplies", sr.Spid, sr.Content));
            }
        }
    }
}
