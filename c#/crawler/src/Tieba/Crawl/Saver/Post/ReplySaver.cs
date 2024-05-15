using PredicateBuilder = LinqKit.PredicateBuilder;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public partial class ReplySaver(
        ILogger<ReplySaver> logger,
        ConcurrentDictionary<PostId, ReplyPost> posts,
        ReplySignatureSaver replySignatureSaver,
        AuthorRevisionSaver.New authorRevisionSaverFactory)
    : PostSaver<ReplyPost, BaseReplyRevision>(
        logger, posts, authorRevisionSaverFactory, PostType.Reply)
{
    public delegate ReplySaver New(ConcurrentDictionary<PostId, ReplyPost> posts);

    protected override bool FieldUpdateIgnorance
        (string propName, object? oldValue, object? newValue) => propName switch
    { // possible randomly respond with null
        nameof(ReplyPost.SignatureId) when newValue is null && oldValue is not null => true,
        _ => false
    };

    protected override bool UserFieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => propName switch
    { // FansNickname in reply response will always be null
        nameof(User.FansNickname) when newValue is null && oldValue is not null => true,
        _ => false
    };

    protected override bool UserFieldRevisionIgnorance(string propName, object? oldValue, object? newValue) => propName switch
    { // user icon will be null after UserParser.ResetUsersIcon() get invoked
        nameof(User.Icon) when newValue is not null && oldValue is null => true,
        _ => false
    };

    protected override Dictionary<Type, AddRevisionDelegate>
        AddRevisionDelegatesKeyBySplitEntityType { get; } = new()
    {
        {
            typeof(ReplyRevision.SplitFloor), (db, revisions) =>
                db.Set<ReplyRevision.SplitFloor>()
                    .AddRange(revisions.OfType<ReplyRevision.SplitFloor>())
        },
        {
            typeof(ReplyRevision.SplitSubReplyCount), (db, revisions) =>
                db.Set<ReplyRevision.SplitSubReplyCount>()
                    .AddRange(revisions.OfType<ReplyRevision.SplitSubReplyCount>())
        },
        {
            typeof(ReplyRevision.SplitAgreeCount), (db, revisions) =>
                db.Set<ReplyRevision.SplitAgreeCount>()
                    .AddRange(revisions.OfType<ReplyRevision.SplitAgreeCount>())
        }
    };

    [SuppressMessage("StyleCop.CSharp.SpacingRules", "SA1025:Code should not contain multiple whitespace in a row")]
    protected override NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName) => fieldName switch
    {
        nameof(ReplyPost.IsFold)        => 1 << 2,
        nameof(ReplyPost.DisagreeCount) => 1 << 4,
        nameof(ReplyPost.Geolocation)   => 1 << 5,
        _ => 0
    };

    public override SaverChangeSet<ReplyPost> Save(CrawlerDbContext db)
    {
        var changeSet = Save(db, r => r.Pid,
            r => new ReplyRevision {TakenAt = r.UpdatedAt ?? r.CreatedAt, Pid = r.Pid},
            PredicateBuilder.New<ReplyPost>(r => Posts.Keys.Contains(r.Pid)));

        db.ReplyContents.AddRange(changeSet.NewlyAdded
            .Select(r => new ReplyContent {Pid = r.Pid, ProtoBufBytes = r.Content}));
        SaveReplyContentImages(db, changeSet.NewlyAdded);
        PostSaveHandlers += AuthorRevisionSaver.SaveAuthorExpGradeRevisions(db, changeSet.AllAfter).Invoke;
        PostSaveHandlers += replySignatureSaver.SaveReplySignatures(db, changeSet.AllAfter).Invoke;

        return changeSet;
    }
}
public partial class ReplySaver
{
    private static void SaveReplyContentImages(CrawlerDbContext db, IEnumerable<ReplyPost> replies)
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
        db.ReplyContentImages.AddRange(pidAndImageList.Select(t => new ReplyContentImage
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
