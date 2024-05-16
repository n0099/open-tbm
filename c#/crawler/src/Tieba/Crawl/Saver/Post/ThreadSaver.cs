using LinqKit;

namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public class ThreadSaver(
        ILogger<ThreadSaver> logger,
        ConcurrentDictionary<Tid, ThreadPost> posts,
        AuthorRevisionSaver.New authorRevisionSaverFactory)
    : PostSaver<ThreadPost, BaseThreadRevision>(
        logger, posts, authorRevisionSaverFactory, PostType.Thread)
{
    public delegate ThreadSaver New(ConcurrentDictionary<Tid, ThreadPost> posts);

    protected override bool FieldUpdateIgnorance
        (string propName, object? oldValue, object? newValue) => propName switch
    { // will be updated by ThreadLateCrawler and ThreadLateCrawlFacade
        nameof(ThreadPost.AuthorPhoneType) => true,
        // prevent overwrite existing value of field liker_id which is saved by legacy crawler
        // and Zan itself is deprecated by tieba, so it shouldn't get updated
        nameof(ThreadPost.Zan) => true,
        // possible randomly respond with null
        nameof(ThreadPost.Geolocation) when newValue is null => true,
        // empty string means the author had not written a title
        // its value generated from the first reply within response of reply crawler
        // will be later set by ReplyCrawlFacade.SaveParentThreadTitle()
        nameof(ThreadPost.Title)
            when newValue is ""

            // prevent repeatedly update with different title
            // due to the thread is a multi forum topic thread
            // thus its title can be varied within the forum and within the thread
            || (newValue is not "" && oldValue is not "") => true,
        // possible randomly respond with 0.NullIfZero()
        nameof(ThreadPost.DisagreeCount) when newValue is null && oldValue is not null => true,
        // when the latest reply post is deleted and there's no new reply after delete
        // this field but not LatestReplyPostedAt will be null
        nameof(ThreadPost.LatestReplierUid) when newValue is null => true,
        _ => false
    };

    protected override bool FieldRevisionIgnorance
        (string propName, object? oldValue, object? newValue) => propName switch
    { // empty string from response has been updated by ReplyCrawlFacade.OnPostParse()
        nameof(ThreadPost.Title) when oldValue is "" => true,
        // null values will be later set by tieba client 6.0.2 response at ThreadParser.ParseInternal()
        nameof(ThreadPost.LatestReplierUid) when oldValue is null => true,
        _ => false
    };

    public override bool UserFieldUpdateIgnorance
        (string propName, object? oldValue, object? newValue) => propName switch
    { // Icon.SpriteInfo will be an empty array and the icon url is a smaller one
        // so we should mark it as null temporarily
        // note this will cause we can't record when did a user update its iconinfo to null
        // since these null values have been ignored in ReplySaver and SubReplySaver
        nameof(User.Icon) => true,
        _ => false
    };

    protected override Dictionary<Type, AddRevisionDelegate>
        AddRevisionDelegatesKeyBySplitEntityType { get; } = new()
    {
        {
            typeof(ThreadRevision.SplitViewCount), (db, revisions) =>
                db.Set<ThreadRevision.SplitViewCount>()
                    .AddRange(revisions.OfType<ThreadRevision.SplitViewCount>())
        }
    };

    [SuppressMessage("StyleCop.CSharp.SpacingRules", "SA1025:Code should not contain multiple whitespace in a row")]
    protected override NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName) => fieldName switch
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

    public override SaverChangeSet<ThreadPost> Save(CrawlerDbContext db) =>
        Save(db, th => th.Tid,
            th => new ThreadRevision {TakenAt = th.UpdatedAt ?? th.CreatedAt, Tid = th.Tid},
            PredicateBuilder.New<ThreadPost>(th => Posts.Keys.Contains(th.Tid)));
}
