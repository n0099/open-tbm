namespace tbm.Crawler.Tieba.Crawl.Saver;

public class ReplyContentImageSaver(SaverLocks<string> locks)
{
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
        (from existing in existingImages.Values
                where existing.ExpectedByteSize == 0 // randomly respond with 0
                join newInContent in imagesKeyByUrlFilename.Values
                    on existing.UrlFilename equals newInContent.UrlFilename
                select (existing, newInContent))
            .ForEach(t => t.existing.ExpectedByteSize = t.newInContent.ExpectedByteSize);
        var newImagesUrlFilename = imagesKeyByUrlFilename.ExceptByKey(existingImages.Keys).Keys().ToList();
        db.ReplyContentImages.AddRange(pidAndImageList
            .ExceptBy(locks.AcquireLocks(newImagesUrlFilename), t => t.Image.UrlFilename)
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
    }
}
