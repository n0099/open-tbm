namespace tbm.Crawler.Worker;

public class SimplifyImagesInAllReplyContentsWorker(
    ILogger<SimplifyImagesInAllReplyContentsWorker> logger,
    Func<Owned<CrawlerDbContext.NewDefault>> dbContextDefaultFactory,
    Func<Owned<CrawlerDbContext.New>> dbContextFactory)
    : TransformEntityWorker<CrawlerDbContext, ReplyContent, ReplyContent, Pid>(logger)
{
    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await using var db = dbContextDefaultFactory().Value();
        foreach (var fid in from e in db.Forums select e.Fid)
        {
            await Transform(
                () => dbContextFactory().Value(fid),
                saveByNthEntityCount: 10000,
                writingEntityEntry =>
                {
                    var p = writingEntityEntry.Property(e => e.ProtoBufBytes);
                    p.IsModified = !ByteArrayEqualityComparer.Instance.Equals(p.OriginalValue, p.CurrentValue);
                },
                readingEntity => readingEntity.Pid,
                readingEntity =>
                {
                    var protoBuf = Reply.Parser.ParseFrom(readingEntity.ProtoBufBytes);
                    ReplyParser.SimplifyImagesInReplyContent(logger, ref protoBuf);
                    return new() {Pid = readingEntity.Pid, ProtoBufBytes = protoBuf.ToByteArray()};
                },
                stoppingToken);
        }
    }
}
