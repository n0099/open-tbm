using LinqKit;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public class SubReplySaver(
        ILogger<SubReplySaver> logger,
        ConcurrentDictionary<PostId, SubReplyPost> posts,
        AuthorRevisionSaver.New authorRevisionSaverFactory)
    : BaseSaver<SubReplyPost, BaseSubReplyRevision>(
        logger, posts, authorRevisionSaverFactory, "subReply")
{
    public delegate SubReplySaver New(ConcurrentDictionary<PostId, SubReplyPost> posts);

    public override FieldChangeIgnoranceDelegates UserFieldChangeIgnorance { get; } = new(
        Update: (_, propName, oldValue, newValue) => propName switch
        { // always ignore updates on iconinfo due to some rare user will show some extra icons
            // compare to reply response in the response of sub reply
            nameof(User.Icon) => true,

            // FansNickname in sub reply response will always be null
            nameof(User.FansNickname) when oldValue is not null && newValue is null => true,

            // DisplayName in users embedded in sub replies from response will be the legacy nick name
            nameof(User.DisplayName) => true,
            _ => false
        }, (_, _, _, _) => false);

    protected override Dictionary<Type, RevisionUpsertDelegate>
        RevisionUpsertDelegatesKeyBySplitEntityType { get; } = new()
    {
        {
            typeof(SubReplyRevision.SplitAgreeCount), (db, revisions) =>
                db.Set<SubReplyRevision.SplitAgreeCount>()
                    .UpsertRange(revisions.OfType<SubReplyRevision.SplitAgreeCount>()).NoUpdate().Run()
        },
        {
            typeof(SubReplyRevision.SplitDisagreeCount), (db, revisions) =>
                db.Set<SubReplyRevision.SplitDisagreeCount>()
                    .UpsertRange(revisions.OfType<SubReplyRevision.SplitDisagreeCount>()).NoUpdate().Run()
        }
    };

    public override SaverChangeSet<SubReplyPost> SavePosts(CrawlerDbContext db)
    {
        var changeSet = SavePosts(db, sr => sr.Spid,
            sr => new SubReplyRevision {TakenAt = sr.UpdatedAt ?? sr.CreatedAt, Spid = sr.Spid},
            PredicateBuilder.New<SubReplyPost>(sr => Posts.Keys.Contains(sr.Spid)));

        db.SubReplyContents.AddRange(changeSet.NewlyAdded.Select(sr =>
            new SubReplyContent {Spid = sr.Spid, ProtoBufBytes = sr.Content}));
        PostSaveEvent += AuthorRevisionSaver.SaveAuthorExpGradeRevisions(db, changeSet.AllAfter).Invoke;

        return changeSet;
    }

    protected override NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName) => 0;
}
