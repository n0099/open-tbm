namespace tbm.Crawler.Worker;

public class ResumeSuspendPostContentsPushingWorker : ErrorableWorker
{
    private readonly ILogger<ResumeSuspendPostContentsPushingWorker> _logger;
    private readonly SonicPusher _pusher;

    public ResumeSuspendPostContentsPushingWorker
        (ILogger<ResumeSuspendPostContentsPushingWorker> logger, SonicPusher pusher) :
        base(logger) => (_logger, _pusher) = (logger, pusher);

    public static string GetFilePath(string postType) =>
        Path.Combine(AppContext.BaseDirectory, $"suspendPostContentsPushIntoSonic.{postType}.csv");

    protected override Task DoWork(CancellationToken stoppingToken)
    {
        foreach (var postType in new List<string> {"replies", "subReplies"})
        {
            var path = GetFilePath(postType);
            if (!File.Exists(path)) continue;
            var postTuples = File.ReadLines(path).Select(ParseLine)
                .OfType<(Fid Fid, PostId Id, string Content)>().ToList();
            postTuples.GroupBy(t => t.Fid).ForEach(g =>
                _pusher.PushPostWithCancellationToken(g.ToList(), g.Key, postType, t => t.Id,
                    t => Helper.ParseThenUnwrapPostContent(Convert.FromBase64String(t.Content)),
                    stoppingToken));
            _logger.LogInformation("Resume for {} suspend {} contents push into sonic finished",
                postTuples.Count, postType);
            File.Delete(path);
        }
        return Task.CompletedTask;
    }

    private (Fid Fid, PostId Id, string Content)? ParseLine(string line)
    {
        if (line.Split(',') is [var fidStr, var postIdStr, var base64EncodedPostContent])
        {
            if (!Fid.TryParse(fidStr, out var fid))
            {
                _logger.LogWarning("Malformed fid {} when resume suspend post contents push into sonic, line={}", fidStr, line);
                return null;
            }
            if (!PostId.TryParse(postIdStr, out var postId))
            {
                _logger.LogWarning("Malformed post id {} when resume suspend post contents push into sonic, line={}", postIdStr, line);
                return null;
            }
            return (fid, postId, base64EncodedPostContent);
        }
        _logger.LogWarning("Malformed line {} when resume suspend post contents push into sonic", line);
        return null;
    }
}
