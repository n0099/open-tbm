using Microsoft.EntityFrameworkCore.ChangeTracking;

namespace tbm.Crawler.Worker;

public class ProcessImagesInAllReplyContentsWorker(
    ILogger<ProcessImagesInAllReplyContentsWorker> logger,
    Func<Owned<CrawlerDbContext.NewDefault>> dbContextDefaultFactory,
    Func<Owned<CrawlerDbContext.New>> dbContextFactory,
    ReplyContentImageSaver replyContentImageSaver)
    : TransformEntityWorker<CrawlerDbContext, ReplyContent, ReplyContent, Pid>(logger)
{
    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        var stopwatch = new Stopwatch();
        stopwatch.Start();
        await using var dbDefaultFactory = dbContextDefaultFactory();
        var db = dbDefaultFactory.Value();
        foreach (var fid in from e in db.Forums select e.Fid)
        {
            logger.LogInformation("Simplify images in reply contents of fid {} started", fid);
            await using var dbFactory = dbContextFactory();
            await Transform(
                () => dbFactory.Value(fid),
                saveWritingEntitiesBatchSize: 10000,
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
                    var protoBuf = PostContentWrapper.Parser.ParseFrom(readingEntity.ProtoBufBytes);
                    var reply = new Reply {Pid = pid, Content = {protoBuf.Value}};
                    ReplyParser.SimplifyImagesInReplyContent(logger, ref reply);
                    var bytes = Helper.SerializedProtoBufWrapperOrNullIfEmpty(reply.Content, Helper.WrapPostContent);
                    writingEntity.ProtoBufBytes = bytes;
                },
                (writingDb, writingEntityEntries) =>
                {
                    writingEntityEntries.ForEach(ee =>
                    {
                        var p = ee.Property(e => e.ProtoBufBytes);
                        p.IsModified = !ByteArrayEqualityComparer.Instance.Equals(p.OriginalValue, p.CurrentValue);
                    });
                    replyContentImageSaver.Save(writingDb, writingEntityEntries
                        .Select(ee => ee.Entity)
                        .Select(e => new ReplyPost
                        {
                            Pid = e.Pid,
                            Content = null!,
                            ContentsProtoBuf = e.ProtoBufBytes == null
                                ? new()
                                : PostContentWrapper.Parser.ParseFrom(e.ProtoBufBytes).Value
                        }))();
                },
                stoppingToken);
            logger.LogInformation("Simplify images in reply contents of fid {} finished after {:F2}s",
                fid, stopwatch.Elapsed.TotalSeconds);
            stopwatch.Restart();
        }
    }
}
