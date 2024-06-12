namespace tbm.Crawler.Tieba.Crawl.Saver;

public class ReplyContentImageSaver(ILogger<ReplyContentImageSaver> logger)
{
    private static readonly ConcurrentDictionary<string, ImageInReply>
        GlobalLockedImagesInReplyKeyByUrlFilename = new();

    public Action Save(CrawlerDbContext db, IEnumerable<ReplyPost> replies)
    {
        var replyContentImages = (
                from r in replies
                from c in r.ContentsProtoBuf
                where c.Type == 3
                where // only save image filename without extension that extracted from url by ReplyParser.Convert()
                    ReplyParser.ValidateContentImageFilenameRegex().IsMatch(c.OriginSrc)
                select new ReplyContentImage
                {
                    Pid = r.Pid,
                    ImageInReply = new()
                    {
                        UrlFilename = c.OriginSrc,
                        ExpectedByteSize = c.OriginSize
                    }
                })
            .DistinctBy(t => (t.Pid, t.ImageInReply.UrlFilename))
            .ToList();
        if (replyContentImages.Count == 0) return () => { };
        var images = replyContentImages.Select(t => t.ImageInReply)
            .DistinctBy(image => image.UrlFilename).ToDictionary(image => image.UrlFilename);

        var existingImages = (
                from e in db.ImageInReplies.AsTracking()
                where images.Keys.Contains(e.UrlFilename)
                select e)
            .ToDictionary(e => e.UrlFilename);
        var newImages = images
            .ExceptByKey(existingImages.Keys).ToDictionary();

        var newlyLockedImages = newImages
            .Where(pair => GlobalLockedImagesInReplyKeyByUrlFilename.TryAdd(pair.Key, pair.Value))
            .ToDictionary();
        newlyLockedImages.Values()
            .Where(image => !Monitor.TryEnter(image, TimeSpan.FromSeconds(10)))
            .ForEach(image => logger.LogWarning(
                "Wait for locking newly locked image {} timed out after 10s", image.UrlFilename));

        var alreadyLockedImages = GlobalLockedImagesInReplyKeyByUrlFilename
            .IntersectByKey(newImages
                .Keys().Except(newlyLockedImages.Keys()))
            .ToDictionary();
        alreadyLockedImages.Values()
            .Where(image => !Monitor.TryEnter(image, TimeSpan.FromSeconds(10)))
            .ForEach(image => logger.LogWarning(
                "Wait for locking already locked image {} timed out after 10s", image.UrlFilename));

        if (alreadyLockedImages.Count != 0)
            existingImages = existingImages
                .Concat((
                    from e in db.ImageInReplies.AsTracking()
                    where alreadyLockedImages.Keys().Contains(e.UrlFilename)
                    select e).ToDictionary(e => e.UrlFilename))
                .ToDictionary();
        (from existing in existingImages.Values
                where existing.ExpectedByteSize == 0 // randomly respond with 0
                join newInContent in images.Values
                    on existing.UrlFilename equals newInContent.UrlFilename
                select (existing, newInContent))
            .ForEach(t => t.existing.ExpectedByteSize = t.newInContent.ExpectedByteSize);

        (from existing in existingImages.Values
                join replyContentImage in replyContentImages
                    on existing.UrlFilename equals replyContentImage.ImageInReply.UrlFilename
                select (existing, replyContentImage))
            .ForEach(t => t.replyContentImage.ImageInReply = t.existing);
        var existingReplyContentImages = db.ReplyContentImages.AsNoTracking()
            .Where(replyContentImages.Aggregate(
                LinqKit.PredicateBuilder.New<ReplyContentImage>(),
                (predicate, newOrExisting) =>
                    predicate.Or(LinqKit.PredicateBuilder
                        .New<ReplyContentImage>(existing =>
                            existing.Pid == newOrExisting.Pid)
                        .And(existing =>
                            existing.ImageInReply.UrlFilename == newOrExisting.ImageInReply.UrlFilename))))
            .Include(e => e.ImageInReply)
            .Select(e => new {e.Pid, e.ImageInReply.UrlFilename})
            .ToList();
        db.ReplyContentImages.AddRange(replyContentImages
            .ExceptBy(existingReplyContentImages.Select(e => (e.Pid, e.UrlFilename)),
                e => (e.Pid, e.ImageInReply.UrlFilename)));

        return () =>
        {
            try
            {
                if (newlyLockedImages.Any(pair =>
                        !GlobalLockedImagesInReplyKeyByUrlFilename.TryRemove(pair)))
                    throw new InvalidOperationException();
            }
            finally
            {
                newlyLockedImages.Values().ForEach(Monitor.Exit);
                alreadyLockedImages.Values().ForEach(Monitor.Exit);
            }
        };
    }
}
