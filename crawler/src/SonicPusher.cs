using NSonic;

namespace tbm.Crawler
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
            var content = PostContentWrapper.Parser.ParseFrom(postContent).Value
                .Where(c => c.Type != 2) // filter out emoticons alt text
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
}
