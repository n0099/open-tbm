namespace tbm.Crawler.Tieba.Crawl.Saver;

public class ReplyContentImageSaver(ILogger<ReplyContentImageSaver> logger)
{
    private static readonly ConcurrentDictionary<string, ImageInReply>
        GlobalLockedImagesInReplyKeyByUrlFilename = new();

    public Action Save(CrawlerDbContext db, IEnumerable<ReplyPost> replies)
    {
        var pidAndImageList = (
                from r in replies
                from c in r.OriginalContents
                where c.Type == 3
                where // only save image filename without extension that extracted from url by ReplyParser.Convert()
                    ReplyParser.ValidateContentImageFilenameRegex().IsMatch(c.OriginSrc)
                select (r.Pid, Image: new ImageInReply
                {
                    UrlFilename = c.OriginSrc,
                    ExpectedByteSize = c.OriginSize
                }))
            .DistinctBy(t => (t.Pid, t.Image.UrlFilename))
            .ToList();
        if (pidAndImageList.Count == 0) return () => { };
        var images = pidAndImageList.Select(t => t.Image)
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
            .Where(reply => !Monitor.TryEnter(reply, TimeSpan.FromSeconds(10)))
            .ForEach(image => logger.LogWarning(
                "Wait for locking newly locked image {} timed out after 10s", image.UrlFilename));

        var alreadyLockedImages = GlobalLockedImagesInReplyKeyByUrlFilename
            .IntersectByKey(newImages
                .Keys().Except(newlyLockedImages.Keys()))
            .ToDictionary();
        alreadyLockedImages.Values()
            .Where(reply => !Monitor.TryEnter(reply, TimeSpan.FromSeconds(10)))
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
        db.ReplyContentImages.AddRange(pidAndImageList
            .Select(t => new ReplyContentImage
            {
                Pid = t.Pid,

                // no need to manually invoke DbContext.AddRange(images) since EF Core will do these chore
                // https://stackoverflow.com/questions/5212751/how-can-i-retrieve-id-of-inserted-entity-using-entity-framework/41146434#41146434
                // reuse the same instance from existingImages
                // will prevent assigning multiple different instances with the same key
                // which will cause EF Core to insert identify entry more than one time leading to duplicated entry error
                // https://github.com/dotnet/efcore/issues/30236
                ImageInReply = existingImages.TryGetValue(t.Image.UrlFilename, out var e)
                    ? e
                    : images[t.Image.UrlFilename]
            }));

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
