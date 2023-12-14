using LinqKit;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public class ThreadSaver(
        ILogger<ThreadSaver> logger,
        ConcurrentDictionary<Tid, ThreadPost> posts,
        AuthorRevisionSaver.New authorRevisionSaverFactory)
    : BaseSaver<ThreadPost, BaseThreadRevision>(
        logger, posts, authorRevisionSaverFactory, "thread")
{
    public delegate ThreadSaver New(ConcurrentDictionary<Tid, ThreadPost> posts);

    public override FieldChangeIgnoranceDelegates TiebaUserFieldChangeIgnorance { get; } = new(
        Update: (_, propName, _, _) => propName switch
        { // Icon.SpriteInfo will be an empty array and the icon url is a smaller one
            // so we should mark it as null temporarily
            // note this will cause we can't record when did a user update its iconinfo to null
            // since these null values have been ignored in reply and sub reply saver
            nameof(TiebaUser.Icon) => true,
            _ => false
        }, (_, _, _, _) => false);

    protected override Dictionary<Type, RevisionUpsertDelegate>
        RevisionUpsertDelegatesKeyBySplitEntityType { get; } = new()
    {
        {
            typeof(ThreadRevision.SplitViewCount), (db, revisions) =>
                db.Set<ThreadRevision.SplitViewCount>()
                    .UpsertRange(revisions.OfType<ThreadRevision.SplitViewCount>()).NoUpdate().Run()
        }
    };

    public override SaverChangeSet<ThreadPost> SavePosts(CrawlerDbContext db) =>
        SavePosts(db, th => th.Tid,
            th => new ThreadRevision {TakenAt = th.UpdatedAt ?? th.CreatedAt, Tid = th.Tid},
            PredicateBuilder.New<ThreadPost>(th => Posts.Keys.Contains(th.Tid)));

    [SuppressMessage("StyleCop.CSharp.SpacingRules", "SA1025:Code should not contain multiple whitespace in a row")]
    protected override ushort GetRevisionNullFieldBitMask(string fieldName) => fieldName switch
    {
        nameof(ThreadPost.StickyType)       => 1,
        nameof(ThreadPost.TopicType)        => 1 << 1,
        nameof(ThreadPost.IsGood)           => 1 << 2,
        nameof(ThreadPost.LatestReplierUid) => 1 << 4,
        nameof(ThreadPost.ReplyCount)       => 1 << 5,
        nameof(ThreadPost.ShareCount)       => 1 << 7,
        nameof(ThreadPost.AgreeCount)       => 1 << 8,
        nameof(ThreadPost.DisagreeCount)    => 1 << 9,
        nameof(ThreadPost.Geolocation)      => 1 << 10,
        _ => 0
    };
}
