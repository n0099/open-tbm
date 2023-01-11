using System.Linq.Expressions;
using LinqToDB;
using LinqToDB.EntityFrameworkCore;

namespace tbm.Crawler.Tieba.Crawl.Saver
{
    public class AuthorRevisionSaver
    {
        // locks only using fid and uid field values from AuthorRevision
        // this prevents inserting multiple entities with similar time and other fields with the same values
        private static readonly HashSet<(Fid Fid, long Uid)> AuthorExpGradeLocks = new();
        private readonly List<(Fid Fid, long Uid)> _savedRevisions = new();
        private readonly string _triggeredByPostType;

        public delegate AuthorRevisionSaver New(string triggeredByPostType);

        public AuthorRevisionSaver(string triggeredByPostType) => _triggeredByPostType = triggeredByPostType;

        private class LatestAuthorRevisionProjection<TValue>
        {
            public long Uid { get; init; }
            public TValue? Value { get; init; }
            public long Rank { get; init; }
        }

        public Action SaveAuthorExpGradeRevisions<TPostWithAuthorExpGrade>
            (TbmDbContext db, ICollection<TPostWithAuthorExpGrade> posts)
            where TPostWithAuthorExpGrade : IPost, IPostWithAuthorExpGrade
        {
            SaveAuthorRevisions(db, posts, AuthorExpGradeLocks,
                db.AuthorExpGradeRevisions,
                p => p.AuthorExpGrade,
                (a, b) => a != b,
                r => new()
                {
                    Uid = r.Uid,
                    Value = r.AuthorExpGrade,
                    Rank = Sql.Ext.Rank().Over().PartitionBy(r.Uid).OrderByDesc(r.DiscoveredAt).ToValue()
                },
                tuple => new()
                {
                    DiscoveredAt = tuple.DiscoveredAt,
                    Fid = db.Fid,
                    Uid = tuple.Uid,
                    TriggeredBy = _triggeredByPostType,
                    AuthorExpGrade = tuple.Value
                });
            return () => ReleaseAllLocks(AuthorExpGradeLocks);
        }

        private void SaveAuthorRevisions<TPost, TRevision, TValue>(TbmDbContext db,
            ICollection<TPost> posts,
            HashSet<(Fid Fid, long Uid)> locks,
            IQueryable<TRevision> dbSet,
            Func<TPost, TValue?> postAuthorFieldValueSelector,
            Func<TValue?, TValue?, bool> isValueChangedPredicate,
            Expression<Func<TRevision, LatestAuthorRevisionProjection<TValue>>> latestRevisionProjectionFactory,
            Func<(long Uid, TValue? Value, Time DiscoveredAt), TRevision> revisionFactory)
            where TRevision : AuthorRevision where TPost : IPost
        {
            var now = (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
            _ = dbSet.AsNoTracking().TagWith("ForUpdate") // https://github.com/linq2db/linq2db/issues/3905
                .Where(e => e.Fid == db.Fid && posts.Select(p => p.AuthorUid).Distinct().Contains(e.Uid)).ToList();
            var existingRevisionOfExistingUsers = dbSet.AsNoTracking()
                .Where(e => e.Fid == db.Fid && posts.Select(p => p.AuthorUid).Distinct().Contains(e.Uid))
                .Select(latestRevisionProjectionFactory)
                .Where(e => e.Rank == 1)
                .ToLinqToDB().ToList()
                .Join(posts, e => e.Uid, p => p.AuthorUid, (e, p) =>
                    (e.Uid, existing: e.Value, newInPost: postAuthorFieldValueSelector(p), DiscoveredAt: now))
                .ToList();
            var newRevisionOfNewUsers = posts
                .ExceptBy(existingRevisionOfExistingUsers.Select(tuple => tuple.Uid), p => p.AuthorUid)
                .Select(p => (Uid: p.AuthorUid, Value: postAuthorFieldValueSelector(p), DiscoveredAt: now));
            var newRevisionOfExistingUsers = existingRevisionOfExistingUsers
                .Where(tuple => isValueChangedPredicate(tuple.existing, tuple.newInPost))
                .Select(tuple => (tuple.Uid, Value: tuple.newInPost, tuple.DiscoveredAt));
            lock (locks)
            {
                var newRevisionsExceptLocked = newRevisionOfNewUsers
                    .Concat(newRevisionOfExistingUsers)
                    .Select(revisionFactory)
                    .ExceptBy(locks, r => (r.Fid, r.Uid))
                    .ToList();
                if (!newRevisionsExceptLocked.Any()) return;

                _savedRevisions.AddRange(newRevisionsExceptLocked.Select(r => (r.Fid, r.Uid)));
                locks.UnionWith(_savedRevisions);
                db.Set<TRevision>().AddRange(newRevisionsExceptLocked);
            }
        }

        private void ReleaseAllLocks(HashSet<(Fid Fid, long Uid)> locks)
        {
            lock (locks) locks.ExceptWith(_savedRevisions);
        }
    }
}
