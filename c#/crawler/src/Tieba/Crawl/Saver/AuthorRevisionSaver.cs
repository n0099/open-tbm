namespace tbm.Crawler.Tieba.Crawl.Saver;

// locks only using AuthorRevision.Fid and Uid, ignoring TriggeredBy
// this prevents inserting multiple entities with similar time and other fields with the same values
public class AuthorRevisionSaver(
    ILogger<AuthorRevisionSaver> logger,
    SaverLocks<(Fid Fid, Uid Uid)> authorExpGradeLocks,
    PostType triggeredByPostType)
{
    public delegate AuthorRevisionSaver New(PostType triggeredByPostType);

    public Action SaveAuthorExpGradeRevisions<TPostWithAuthorExpGrade>
        (CrawlerDbContext db, IReadOnlyCollection<TPostWithAuthorExpGrade> posts)
        where TPostWithAuthorExpGrade : PostWithAuthorExpGrade
    {
        Save(db, posts, authorExpGradeLocks,
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
        return authorExpGradeLocks.Dispose;
    }

    private void Save<TPost, TRevision, TValue>(
        CrawlerDbContext db,
        IReadOnlyCollection<TPost> posts,
        SaverLocks<(Fid Fid, Uid Uid)> locks,
        IQueryable<TRevision> dbSet,
        Func<TPost, TValue?> postRevisioningFieldSelector,
        Func<TValue?, TValue?, bool> isValueChangedPredicate,
        Expression<Func<TRevision, LatestAuthorRevisionProjection<TValue>>> latestRevisionProjectionFactory,
        Func<(Uid Uid, TValue? Value, Time DiscoveredAt), TRevision> revisionFactory)
        where TPost : BasePost
        where TRevision : AuthorRevision
    { // only takes the first of multiple post from the same author
        var uniquePosts = posts.DistinctBy(p => p.AuthorUid).ToList();
        if (uniquePosts.Count != posts.Count)
            Helper.LogDifferentValuesSharingTheSameKeyInEntities(logger, posts,
            $"{nameof(TPost)}.{nameof(BasePost.AuthorUid)}",
            p => p.AuthorUid,
            postRevisioningFieldSelector);

        SharedHelper.GetNowTimestamp(out var now);
        var existingRevisionOfExistingUsers = dbSet.AsNoTracking()
            .Where(e => e.Fid == db.Fid
                        && uniquePosts.Select(p => p.AuthorUid).Contains(e.Uid))
            .Select(latestRevisionProjectionFactory)
            .AsCte() // https://stackoverflow.com/questions/49854322/usage-of-for-update-in-window-function-postgres#comment86726589_49854322
            .Where(e => e.Rank == 1)
            .ToLinqToDB().AsEnumerable()
            .Join(uniquePosts, e => e.Uid, p => p.AuthorUid, (e, p) =>
            (
                e.Uid,
                Existing: (e.DiscoveredAt, e.Value),
                NewInPost: (DiscoveredAt: now, Value: postRevisioningFieldSelector(p))
            )).ToList();
        var newRevisionOfExistingUsers = existingRevisionOfExistingUsers

            // filter out revisions with the same DiscoveredAt to prevent duplicate keys
            // when some fields get updated more than once in a second
            .Where(t => t.Existing.DiscoveredAt != t.NewInPost.DiscoveredAt
                        && isValueChangedPredicate(t.Existing.Value, t.NewInPost.Value))
            .Select(t => (t.Uid, t.NewInPost.Value, t.NewInPost.DiscoveredAt));
        var newRevisionOfNewUsers = uniquePosts
            .ExceptBy(existingRevisionOfExistingUsers.Select(t => t.Uid), p => p.AuthorUid)
            .Select(p => (Uid: p.AuthorUid, Value: postRevisioningFieldSelector(p), DiscoveredAt: now));
        var newRevisions = newRevisionOfNewUsers
            .Concat(newRevisionOfExistingUsers)
            .Select(revisionFactory)
            .ToDictionary(revision => (revision.Fid, revision.Uid), revision => revision);
        db.Set<TRevision>().AddRange(newRevisions
            .IntersectByKey(locks.AcquireLocks(newRevisions.Keys))
            .Values());
    }

    private sealed class LatestAuthorRevisionProjection<TValue>
    {
        public Time DiscoveredAt { get; init; }
        public long Uid { get; init; }
        public TValue? Value { get; init; }
        public long Rank { get; init; }
    }
}
