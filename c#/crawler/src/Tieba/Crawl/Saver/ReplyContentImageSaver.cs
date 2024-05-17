namespace tbm.Crawler.Tieba.Crawl.Saver;

public class ReplyContentImageSaver(SaverLocks<string> locks)
{
    private static readonly ConcurrentDictionary<string, object> LocksKeyByUrlFilename = new();

    public void Save(CrawlerDbContext db, IEnumerable<ReplyPost> replies)
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
        if (pidAndImageList.Count == 0) return;
        var imagesKeyByUrlFilename = pidAndImageList.Select(t => t.Image)
            .DistinctBy(image => image.UrlFilename).ToDictionary(image => image.UrlFilename);

        var existingImages = (
                from e in db.ImageInReplies.AsTracking()
                where imagesKeyByUrlFilename.Keys.Contains(e.UrlFilename)
                select e)
            .ToDictionary(e => e.UrlFilename);
        var newImages = imagesKeyByUrlFilename.ExceptByKey(existingImages.Keys).Keys().ToList();
        var newlyLocked = locks.AcquireLocks(newImages);
        var alreadyLocked = newImages.Except(newlyLocked).ToList();

        if (newlyLocked.Any(urlFilename => !LocksKeyByUrlFilename.TryAdd(urlFilename, new())))
            throw new InvalidOperationException();
        alreadyLocked.ForEach(urlFilename =>
        {
            lock (LocksKeyByUrlFilename[urlFilename]) { }
        });
        existingImages = existingImages
            .Concat((
                from e in db.ImageInReplies.AsTracking()
                where alreadyLocked.Contains(e.UrlFilename)
                select e).ToDictionary(e => e.UrlFilename))
            .ToDictionary();

        (from existing in existingImages.Values
                where existing.ExpectedByteSize == 0 // randomly respond with 0
                join newInContent in imagesKeyByUrlFilename.Values
                    on existing.UrlFilename equals newInContent.UrlFilename
                select (existing, newInContent))
            .ForEach(t => t.existing.ExpectedByteSize = t.newInContent.ExpectedByteSize);
        db.ReplyContentImages.AddRange(pidAndImageList
            .Select(t => new ReplyContentImage
            {
                Pid = t.Pid,

                // no need to manually invoke DbContext.AddRange(images) since EF Core will do these chore
                // https://stackoverflow.com/questions/5212751/how-can-i-retrieve-id-of-inserted-entity-using-entity-framework/41146434#41146434
                // reuse the same instance from imagesKeyByUrlFilename
                // will prevent assigning multiple different instances with the same key
                // which will cause EF Core to insert identify entry more than one time leading to duplicated entry error
                // https://github.com/dotnet/efcore/issues/30236
                ImageInReply = existingImages.TryGetValue(t.Image.UrlFilename, out var e)
                    ? e
                    : imagesKeyByUrlFilename[t.Image.UrlFilename]
            }));

        if (newlyLocked.Any(urlFilename => !LocksKeyByUrlFilename.TryRemove(urlFilename, out _)))
            throw new InvalidOperationException();
        locks.Dispose();
    }
}
