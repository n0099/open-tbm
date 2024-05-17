using PredicateBuilder = LinqKit.PredicateBuilder;

namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public class ReplySaver(
    ILogger<ReplySaver> logger,
    ConcurrentDictionary<PostId, ReplyPost> posts,
    ReplyContentImageSaver replyContentImageSaver,
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

    public override bool UserFieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => propName switch
    { // FansNickname in reply response will always be null
        nameof(User.FansNickname) when newValue is null && oldValue is not null => true,
        _ => false
    };

    public override bool UserFieldRevisionIgnorance(string propName, object? oldValue, object? newValue) => propName switch
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

        replyContentImageSaver.Save(db, changeSet.NewlyAdded);
        PostSaveHandlers += AuthorRevisionSaver.SaveAuthorExpGradeRevisions(db, changeSet.AllAfter).Invoke;
        PostSaveHandlers += replySignatureSaver.Save(db, changeSet.AllAfter).Invoke;

        return changeSet;
    }
}
