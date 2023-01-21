namespace tbm.Crawler.Worker;

public class ResumeSuspendPostContentsPushingWorker : IHostedService
{
    private readonly ILogger<ResumeSuspendPostContentsPushingWorker> _logger;
    private readonly SonicPusher _pusher;

    public ResumeSuspendPostContentsPushingWorker(ILogger<ResumeSuspendPostContentsPushingWorker> logger, SonicPusher pusher)
    {
        _logger = logger;
        _pusher = pusher;
    }

    public static string GetFilePath(string postType) =>
        Path.Combine(AppContext.BaseDirectory, $"suspendPostContentsPushIntoSonic.{postType}.csv");

    public async Task StartAsync(CancellationToken cancellationToken)
    {
        foreach (var postType in new List<string> {"replies", "subReplies"})
        {
            var path = GetFilePath(postType);
            if (!File.Exists(path)) continue;
            var postIdKeyByFid = new Dictionary<Fid, List<(PostId Id, string Content)>>();
            await foreach (var line in File.ReadLinesAsync(path, cancellationToken))
            {
                if (line.Split(',') is [var fidStr, var postIdStr, var base64EncodedPostContent])
                {
                    if (!Fid.TryParse(fidStr, out var fid))
                        _logger.LogWarning("Malformed fid {} when resume suspend post contents push into sonic, line={}", fidStr, line);
                    if (!PostId.TryParse(postIdStr, out var postId))
                        _logger.LogWarning("Malformed post id {} when resume suspend post contents push into sonic, line={}", postIdStr, line);
                    var postTuple = (postId, base64EncodedPostContent);
                    if (!postIdKeyByFid.TryAdd(fid, new() {postTuple})) postIdKeyByFid[fid].Add(postTuple);
                }
                else
                {
                    _logger.LogWarning("Malformed line {} when resume suspend post contents push into sonic", line);
                }
            }
            postIdKeyByFid.ForEach(pair =>
            {
                var (fid, posts) = pair;
                _pusher.PushPostWithCancellationToken(posts, fid, postType, t => t.Id,
                    t => Convert.FromBase64String(t.Content), cancellationToken);
            });
            _logger.LogInformation("resume for {} suspend {} contents push into sonic finished",
                postIdKeyByFid.Sum(pair => pair.Value.Count), postType);
            File.Delete(path);
        }
    }

    public Task StopAsync(CancellationToken cancellationToken) => Task.CompletedTask;
}
