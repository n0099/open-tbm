using LinqToDB.DataProvider.PostgreSQL;

namespace tbm.Crawler.Tieba.Crawl.Saver;

// locks only using AuthorRevision.Fid and Uid, ignoring TriggeredBy
// this prevents inserting multiple entities with similar time and other fields with the same values
public class AuthorRevisionSaver(
    SaverLocks<(Fid Fid, Uid Uid)> authorExpGradeLocks,
    PostType triggeredByPostType)
{
    public delegate AuthorRevisionSaver New(PostType triggeredByPostType);

    public Action SaveAuthorExpGradeRevisions<TPostWithAuthorExpGrade>
        (CrawlerDbContext db, IReadOnlyCollection<TPostWithAuthorExpGrade> posts)
        where TPostWithAuthorExpGrade : PostWithAuthorExpGrade
    {
        SaveAuthorRevisions(db, posts, authorExpGradeLocks,
            db.AuthorExpGradeRevisions,
            p => p.AuthorExpGrade,
            (a, b) => a != b,
            rev => new()
            {
                DiscoveredAt = rev.DiscoveredAt,
                Uid = rev.Uid,
                Value = rev.AuthorExpGrade,
                Rank = Sql.Ext.Rank().Over().PartitionBy(rev.Uid).OrderByDesc(rev.DiscoveredAt).ToValue()
            },
            t => new()
            {
                DiscoveredAt = t.DiscoveredAt,
                Fid = db.Fid,
                Uid = t.Uid,
                TriggeredBy = triggeredByPostType,
                AuthorExpGrade = t.Value
            });
        return authorExpGradeLocks.ReleaseLocalLocked;
    }

    private static void SaveAuthorRevisions<TPost, TRevision, TValue>(
        CrawlerDbContext db,
        IReadOnlyCollection<TPost> posts,
        SaverLocks<(Fid Fid, Uid Uid)> locks,
        IQueryable<TRevision> dbSet,
        Func<TPost, TValue?> postAuthorFieldValueSelector,
        Func<TValue?, TValue?, bool> isValueChangedPredicate,
        Expression<Func<TRevision, LatestAuthorRevisionProjection<TValue>>> latestRevisionProjectionFactory,
        Func<(Uid Uid, TValue? Value, Time DiscoveredAt), TRevision> revisionFactory)
        where TPost : BasePost
        where TRevision : AuthorRevision
    {
        SharedHelper.GetNowTimestamp(out var now);
        var existingRevisionOfExistingUsers = dbSet.AsNoTracking()
            .Where(e => e.Fid == db.Fid
                        && posts.Select(p => p.AuthorUid).Distinct().Contains(e.Uid))
            .Select(latestRevisionProjectionFactory)
            .AsCte() // https://stackoverflow.com/questions/49854322/usage-of-for-update-in-window-function-postgres#comment86726589_49854322
            .Where(e => e.Rank == 1)
            .AsPostgreSQL().ForNoKeyUpdateHint()
            .ToLinqToDB().AsEnumerable()
            .Join(posts, e => e.Uid, p => p.AuthorUid, (e, p) =>
            (
                e.Uid,
                Existing: (e.DiscoveredAt, e.Value),
                NewInPost: (DiscoveredAt: now, Value: postAuthorFieldValueSelector(p))
            )).ToList();
        var newRevisionOfExistingUsers = existingRevisionOfExistingUsers

            // filter out revisions with the same DiscoveredAt to prevent duplicate keys
            // when some fields get updated more than one time in a second
            .Where(t => t.Existing.DiscoveredAt != t.NewInPost.DiscoveredAt
                        && isValueChangedPredicate(t.Existing.Value, t.NewInPost.Value))
            .Select(t => (t.Uid, t.NewInPost.Value, t.NewInPost.DiscoveredAt));
        var newRevisionOfNewUsers = posts
            .ExceptBy(existingRevisionOfExistingUsers.Select(t => t.Uid), p => p.AuthorUid)
            .Select(p => (Uid: p.AuthorUid, Value: postAuthorFieldValueSelector(p), DiscoveredAt: now));
        var newRevisions = newRevisionOfNewUsers
            .Concat(newRevisionOfExistingUsers)
            .Select(revisionFactory)
            .ToDictionary(revision => (revision.Fid, revision.Uid), revision => revision);
        db.Set<TRevision>().AddRange(newRevisions
            .IntersectBy(locks.AcquireLocks(newRevisions.Keys), pair => pair.Key)
            .Select(pair => pair.Value));
    }

    private sealed class LatestAuthorRevisionProjection<TValue>
    {
        public Time DiscoveredAt { get; init; }
        public long Uid { get; init; }
        public TValue? Value { get; init; }
        public long Rank { get; init; }
    }
}
