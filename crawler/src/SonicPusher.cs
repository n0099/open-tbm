using NSonic;

namespace tbm.Crawler;

public sealed class SonicPusher : IDisposable
{
    public ISonicIngestConnection Ingest { get; }
    public string CollectionPrefix { get; }
    private static readonly ReaderWriterLockSlim SuspendPushingFileLock = new();
    private readonly ILogger<SonicPusher> _logger;
    private readonly IConfigurationSection _config;

    public SonicPusher(ILogger<SonicPusher> logger, IConfiguration config)
    {
        _logger = logger;
        _config = config.GetSection("Sonic");
        Ingest = NSonicFactory.Ingest(
            _config.GetValue("Hostname", "localhost"),
            _config.GetValue("Port", 1491),
            _config.GetValue("Secret", "SecretPassword")
        );
        CollectionPrefix = _config.GetValue<string>("CollectionPrefix") ?? "tbm_";
    }

    public void Dispose() => Ingest.Dispose();

    public float PushPost(Fid fid, string postType, PostId postId, byte[]? postContent)
    {
        if (!_config.GetValue("Enabled", false)) return 0;
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
            _logger.LogWarning("Pushing a single post content with {} UTF-16 chars into sonic for {} in fid {} spending {:F0}ms, content={}",
                content.Length, postType, fid, ret, content);
        return ret;
    }

    public void PushPostWithCancellationToken<T>(ICollection<T> posts, Fid fid, string postType,
        Func<T, PostId> postIdSelector, Func<T, byte[]?> postContentSelector,
        CancellationToken stoppingToken = default)
    {
        try
        {
            SuspendPushingFileLock.EnterWriteLock();
            posts.ForEach(p =>
            {
                stoppingToken.ThrowIfCancellationRequested();
                _ = PushPost(fid, postType, postIdSelector(p), postContentSelector(p));
            });
        }
        catch (OperationCanceledException e)
        {
            if (e.CancellationToken == stoppingToken)
            {
                string GetBase64EncodedPostContent(T p) =>
                    Convert.ToBase64String(postContentSelector(p) ?? ReadOnlySpan<byte>.Empty);
                File.AppendAllLines(ResumeSuspendPostContentsPushingWorker.GetFilePath(postType),
                    posts.Select(p => $"{fid},{postIdSelector(p)},{GetBase64EncodedPostContent(p)}"));
            }
            throw;
        }
        finally
        {
            SuspendPushingFileLock.ExitWriteLock();
        }
    }
}
