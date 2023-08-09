namespace tbm.Crawler.Worker;

public class ResumeSuspendPostContentsPushingWorker(
        ILogger<ResumeSuspendPostContentsPushingWorker> logger,
        IHostApplicationLifetime applicationLifetime,
        SonicPusher pusher)
    : ErrorableWorker(logger, applicationLifetime)
{
    public static string GetFilePath(string postType) =>
        Path.Combine(AppContext.BaseDirectory, $"suspendPostContentsPushIntoSonic.{postType}.csv");

    protected override Task DoWork(CancellationToken stoppingToken)
    {
        foreach (var postType in new[] {"replies", "subReplies"})
        {
            var path = GetFilePath(postType);
            if (!File.Exists(path)) continue;
            var postTuples = File.ReadLines(path).Select(ParseLine)
                .OfType<(Fid Fid, PostId Id, string Content)>().ToList();
            postTuples.GroupBy(t => t.Fid).ForEach(g =>
                pusher.PushPostWithCancellationToken(g.ToList(), g.Key, postType, t => t.Id,
                    t => Helper.ParseThenUnwrapPostContent(Convert.FromBase64String(t.Content)),
                    stoppingToken));
            logger.LogInformation("Resume for {} suspend {} contents push into sonic finished",
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
                logger.LogWarning("Malformed fid {} when resume suspend post contents push into sonic, line={}", fidStr, line);
                return null;
            }
            if (!PostId.TryParse(postIdStr, out var postId))
            {
                logger.LogWarning("Malformed post id {} when resume suspend post contents push into sonic, line={}", postIdStr, line);
                return null;
            }
            return (fid, postId, base64EncodedPostContent);
        }
        logger.LogWarning("Malformed line {} when resume suspend post contents push into sonic", line);
        return null;
    }
}
