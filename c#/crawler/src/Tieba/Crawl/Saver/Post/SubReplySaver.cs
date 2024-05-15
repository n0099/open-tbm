using LinqKit;

namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public class SubReplySaver(
        ILogger<SubReplySaver> logger,
        ConcurrentDictionary<PostId, SubReplyPost> posts,
        AuthorRevisionSaver.New authorRevisionSaverFactory)
    : PostSaver<SubReplyPost, BaseSubReplyRevision>(
        logger, posts, authorRevisionSaverFactory, PostType.SubReply)
{
    public delegate SubReplySaver New(ConcurrentDictionary<PostId, SubReplyPost> posts);

    protected override bool UserFieldUpdateIgnorance
        (string propName, object? oldValue, object? newValue) => propName switch
    { // always ignore updates on iconinfo due to some rare user will show some extra icons
        // compare to reply response in the response of sub reply
        nameof(User.Icon) => true,

        // FansNickname in sub reply response will always be null
        nameof(User.FansNickname) when newValue is null && oldValue is not null => true,

        // DisplayName in users embedded in sub replies from response will be the legacy nickname
        nameof(User.DisplayName) => true,
        _ => false
    };

    protected override Dictionary<Type, AddRevisionDelegate>
        AddRevisionDelegatesKeyBySplitEntityType { get; } = new()
    {
        {
            typeof(SubReplyRevision.SplitAgreeCount), (db, revisions) =>
                db.Set<SubReplyRevision.SplitAgreeCount>()
                    .AddRange(revisions.OfType<SubReplyRevision.SplitAgreeCount>())
        },
        {
            typeof(SubReplyRevision.SplitDisagreeCount), (db, revisions) =>
                db.Set<SubReplyRevision.SplitDisagreeCount>()
                    .AddRange(revisions.OfType<SubReplyRevision.SplitDisagreeCount>())
        }
    };

    protected override NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName) => 0;

    public override SaverChangeSet<SubReplyPost> Save(CrawlerDbContext db)
    {
        var changeSet = Save(db, sr => sr.Spid,
            sr => new SubReplyRevision {TakenAt = sr.UpdatedAt ?? sr.CreatedAt, Spid = sr.Spid},
            PredicateBuilder.New<SubReplyPost>(sr => Posts.Keys.Contains(sr.Spid)));

        db.SubReplyContents.AddRange(changeSet.NewlyAdded.Select(sr =>
            new SubReplyContent {Spid = sr.Spid, ProtoBufBytes = sr.Content}));
        PostSaveHandlers += AuthorRevisionSaver.SaveAuthorExpGradeRevisions(db, changeSet.AllAfter).Invoke;

        return changeSet;
    }
}
