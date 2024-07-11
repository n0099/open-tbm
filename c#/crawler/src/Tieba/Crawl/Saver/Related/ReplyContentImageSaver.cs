namespace tbm.Crawler.Tieba.Crawl.Saver.Related;

public sealed class ReplyContentImageSaver(ILogger<ReplyContentImageSaver> logger) : IDisposable
{
    private static readonly ConcurrentDictionary<string, ImageInReply>
        GlobalLockedImagesInReplyKeyByUrlFilename = new();
    private readonly Dictionary<string, ImageInReply> _localLockedImages = [];
    private Dictionary<string, ImageInReply>? _alreadyLockedImages;

    public void Dispose()
    {
        try
        {
            _localLockedImages.ForEach(pair =>
            {
                if (!GlobalLockedImagesInReplyKeyByUrlFilename.TryRemove(pair))
                    logger.LogError("Previously locked image {} already removed from the global locks",
                        SharedHelper.UnescapedJsonSerialize(pair));
            });
        }
        finally
        {
            _localLockedImages.Values().ForEach(Monitor.Exit);
            _alreadyLockedImages?.Values().ForEach(Monitor.Exit);
            _localLockedImages.Clear();
            _alreadyLockedImages = null;
        }
    }

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
        var images = replyContentImages
            .Select(replyContentImage => replyContentImage.ImageInReply)
            .DistinctBy(image => image.UrlFilename)
            .ToDictionary(image => image.UrlFilename);

        var existingImages = (
                from e in db.ImageInReplies.AsTracking()
                where images.Keys.Contains(e.UrlFilename)
                select e)
            .ToDictionary(e => e.UrlFilename);
        var newImages = images
            .ExceptByKey(existingImages.Keys).ToDictionary();

        var newlyLockedImages = newImages
            .Where(pair =>
            {
                var added = GlobalLockedImagesInReplyKeyByUrlFilename.TryAdd(pair.Key, pair.Value);
                if (added) _localLockedImages.Add(pair.Key, pair.Value);
                return added;
            })
            .ToDictionary();
        newlyLockedImages.Values()
            .Where(image => !Monitor.TryEnter(image, TimeSpan.FromSeconds(10)))
            .ForEach(image => logger.LogWarning(
                "Wait for locking newly locked image {} timed out after 10s", image.UrlFilename));

        _alreadyLockedImages = GlobalLockedImagesInReplyKeyByUrlFilename
            .IntersectByKey(newImages
                .Keys().Except(newlyLockedImages.Keys()))
            .ToDictionary();
        _alreadyLockedImages.Values()
            .Where(image => !Monitor.TryEnter(image, TimeSpan.FromSeconds(10)))
            .ForEach(image => logger.LogWarning(
                "Wait for locking already locked image {} timed out after 10s", image.UrlFilename));

        if (_alreadyLockedImages.Count != 0)
            existingImages = existingImages
                .Concat((
                    from e in db.ImageInReplies.AsTracking()
                    where _alreadyLockedImages.Keys().Contains(e.UrlFilename)
                    select e).ToDictionary(e => e.UrlFilename))
                .ToDictionary();
        (from existing in existingImages.Values
                where existing.ExpectedByteSize == 0 // randomly respond with 0
                join newInContent in images.Values
                    on existing.UrlFilename equals newInContent.UrlFilename
                select (existing, newInContent))
            .ForEach(t => t.existing.ExpectedByteSize = t.newInContent.ExpectedByteSize);

        (from existingOrNew in existingImages.Values
                    .Concat(newImages.Values)
                    .DistinctBy(image => image.UrlFilename) // the earliest one in order takes the first
                join replyContentImage in replyContentImages
                    on existingOrNew.UrlFilename equals replyContentImage.ImageInReply.UrlFilename
                select (existingOrNew, replyContentImage))
            .ForEach(t => t.replyContentImage.ImageInReply = t.existingOrNew);
        var existingReplyContentImages = db.ReplyContentImages.AsNoTracking()
            .FilterByItems(replyContentImages, (existing, newOrExisting) =>
                existing.Pid == newOrExisting.Pid
                && existing.ImageInReply.UrlFilename == newOrExisting.ImageInReply.UrlFilename)
            .Include(e => e.ImageInReply)
            .Select(e => new {e.Pid, e.ImageInReply.UrlFilename})
            .ToList();
        db.ReplyContentImages.AddRange(replyContentImages
            .ExceptBy(existingReplyContentImages.Select(e => (e.Pid, e.UrlFilename)),
                e => (e.Pid, e.ImageInReply.UrlFilename)));

        return Dispose;
    }
}
