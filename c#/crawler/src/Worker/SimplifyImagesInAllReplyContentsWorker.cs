using Microsoft.EntityFrameworkCore.ChangeTracking;

namespace tbm.Crawler.Worker;

public class SimplifyImagesInAllReplyContentsWorker(
    ILogger<SimplifyImagesInAllReplyContentsWorker> logger,
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
                saveByNthEntityCount: 10000,
                readingEntity => readingEntity.Pid,
                readingEntity => new()
                {
                    Pid = readingEntity.Pid,
                    ProtoBufBytes = readingEntity.ProtoBufBytes,
                    Version = readingEntity.Version
                },
                delegate(
                    ReplyContent readingEntity,
                    ref ReplyContent writingEntity,
                    CrawlerDbContext writingDb,
                    EntityEntry<ReplyContent> writingEntityEntry)
                {
                    if (readingEntity.ProtoBufBytes == null) return;
                    var pid = readingEntity.Pid;
                    var protoBuf = PostContentWrapper.Parser.ParseFrom(readingEntity.ProtoBufBytes);
                    var reply = new Reply {Pid = pid, Content = {protoBuf.Value}};
                    ReplyParser.SimplifyImagesInReplyContent(logger, ref reply);
                    var bytes = Helper.SerializedProtoBufWrapperOrNullIfEmpty(reply.Content, Helper.WrapPostContent);
                    writingEntity.ProtoBufBytes = bytes;

                    var p = writingEntityEntry.Property(e => e.ProtoBufBytes);
                    p.IsModified = !ByteArrayEqualityComparer.Instance.Equals(p.OriginalValue, p.CurrentValue);
                    replyContentImageSaver.Save(writingDb, [new()
                    {
                        Pid = pid,
                        Content = null!,
                        ContentsProtoBuf = reply.Content
                    }])();
                },
                stoppingToken);
            logger.LogInformation("Simplify images in reply contents of fid {} finished after {:F2}s",
                fid, stopwatch.Elapsed.TotalSeconds);
            stopwatch.Restart();
        }
    }
}
