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
        await using var dbDefaultFactory = dbContextDefaultFactory();
        var db = dbDefaultFactory.Value();
        foreach (var fid in from e in db.Forums select e.Fid)
        {
            await using var dbFactory = dbContextFactory();
            await Transform(
                () => dbFactory.Value(fid),
                saveByNthEntityCount: 10000,
                readingEntity => readingEntity.Pid,
                readingEntity =>
                {
                    var protoBuf = Reply.Parser.ParseFrom(readingEntity.ProtoBufBytes);
                    ReplyParser.SimplifyImagesInReplyContent(logger, ref protoBuf);
                    return new() {Pid = readingEntity.Pid, ProtoBufBytes = protoBuf.ToByteArray()};
                },
                writingEntityEntry =>
                {
                    var p = writingEntityEntry.Property(e => e.ProtoBufBytes);
                    p.IsModified = !ByteArrayEqualityComparer.Instance.Equals(p.OriginalValue, p.CurrentValue);
                },
                (writingDb, writingEntities) => replyContentImageSaver
                    .Save(writingDb, writingEntities.Select(e => new ReplyPost
                    {
                        Pid = e.Pid,
                        Content = null!,
                        ContentsProtoBuf = Reply.Parser.ParseFrom(e.ProtoBufBytes).Content
                    })),
                stoppingToken);
        }
    }
}
