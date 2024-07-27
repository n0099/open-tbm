namespace tbm.Crawler.Worker;

// ReSharper disable once UnusedType.Global
public class ProcessImagesInAllReplyContentsWorker(
    ILogger<ProcessImagesInAllReplyContentsWorker> logger,
    IConfiguration config,
    Func<Owned<CrawlerDbContext.NewDefault>> dbContextDefaultFactory,
    Func<Owned<CrawlerDbContext.New>> dbContextFactory,
    ReplyContentImageSaver replyContentImageSaver)
    : TransformEntityWorker<CrawlerDbContext, ReplyContent, ReplyContent, Pid>(logger)
{
    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        var saveWritingEntitiesBatchSize = config
            .GetSection("ProcessImagesInAllReplyContents")
            .GetValue("SaveWritingEntitiesBatchSize", 1000);
        var stopwatch = new Stopwatch();
        stopwatch.Start();
        await using var dbDefaultFactory = dbContextDefaultFactory();
        var db = dbDefaultFactory.Value();
        foreach (var fid in from e in db.Forums select e.Fid)
        {
            logger.LogInformation("Simplify images in reply contents of fid {} started", fid);
            var replyContentsKeyByPid = new Dictionary<Pid, RepeatedField<Content>>(saveWritingEntitiesBatchSize);
            await using var dbFactory = dbContextFactory();
            await Transform(
                () => dbFactory.Value(fid),
                saveWritingEntitiesBatchSize,
                readingEntity => readingEntity.Pid,
                readingEntity => new()
                {
                    Pid = readingEntity.Pid,
                    ProtoBufBytes = readingEntity.ProtoBufBytes,
                    Version = readingEntity.Version
                },
                (readingEntity, writingEntity) =>
                {
                    if (readingEntity.ProtoBufBytes == null) return;
                    var pid = readingEntity.Pid;
                    var reply = new Reply
                    {
                        Pid = pid,
                        Content = {PostContentWrapper.Parser.ParseFrom(readingEntity.ProtoBufBytes).Value}
                    };
                    ReplyParser.SimplifyImagesInReplyContent(logger, ref reply);
                    replyContentsKeyByPid.Add(pid, reply.Content);
                    writingEntity.ProtoBufBytes = Helper
                        .SerializedProtoBufWrapperOrNullIfEmpty(reply.Content, Helper.WrapPostContent);
                },
                (writingDb, writingEntityEntries) =>
                {
                    writingEntityEntries.ForEach(ee =>
                    {
                        var p = ee.Property(e => e.ProtoBufBytes);
                        p.IsModified = !ByteArrayEqualityComparer.Instance.Equals(p.OriginalValue, p.CurrentValue);
                    });
                    _ = replyContentImageSaver.Save(writingDb,
                        replyContentsKeyByPid.Select(pair => new ReplyPost.Parsed
                        {
                            Pid = pair.Key,
                            Content = null!,
                            ContentsProtoBuf = pair.Value
                        }));
                },
                () =>
                {
                    replyContentsKeyByPid.Clear();
                    replyContentImageSaver.Dispose();
                },
                stoppingToken);
            logger.LogInformation("Simplify images in reply contents of fid {} finished after {:F2}s",
                fid, stopwatch.Elapsed.TotalSeconds);
            stopwatch.Restart();
        }
    }
}
