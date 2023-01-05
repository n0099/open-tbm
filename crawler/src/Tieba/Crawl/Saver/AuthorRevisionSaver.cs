using System.Linq.Expressions;
using LinqToDB;
using LinqToDB.EntityFrameworkCore;

namespace tbm.Crawler.Tieba.Crawl.Saver
{
    public class AuthorRevisionSaver
    {
        // locks only using fid and uid field values from AuthorRevision
        // this prevents inserting multiple entities with similar time and other fields with the same values
        private static readonly HashSet<(Fid Fid, long Uid)> AuthorManagerTypeLocks = new();
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

        public Action SaveAuthorManagerTypeRevisions<TPost>
            (TbmDbContext db, ICollection<TPost> postsAfterTimestamping) where TPost : IPost
        {
            SaveAuthorRevisions(db, postsAfterTimestamping, AuthorManagerTypeLocks,
                db.AuthorManagerTypeRevisions,
                p => p.AuthorManagerType,
                (a, b) => a != b,
                r => new()
                {
                    Uid = r.Uid,
                    Value = r.AuthorManagerType,
                    Rank = Sql.Ext.Rank().Over().PartitionBy(r.Uid).OrderByDesc(r.Time).ToValue()
                },
                tuple => new()
                {
                    Time = tuple.PostUpdatedAt,
                    Fid = db.Fid,
                    Uid = tuple.Uid,
                    TriggeredBy = _triggeredByPostType,
                    AuthorManagerType = tuple.Value
                });
            return () => ReleaseAllLocks(AuthorManagerTypeLocks);
        }

        public Action SaveAuthorExpGradeRevisions<TPostWithAuthorExpGrade>
            (TbmDbContext db, ICollection<TPostWithAuthorExpGrade> postsAfterTimestamping)
            where TPostWithAuthorExpGrade : IPost, IPostWithAuthorExpGrade
        {
            SaveAuthorRevisions(db, postsAfterTimestamping, AuthorExpGradeLocks,
                db.AuthorExpGradeRevisions,
                p => p.AuthorExpGrade,
                (a, b) => a != b,
                r => new()
                {
                    Uid = r.Uid,
                    Value = r.AuthorExpGrade,
                    Rank = Sql.Ext.Rank().Over().PartitionBy(r.Uid).OrderByDesc(r.Time).ToValue()
                },
                tuple => new()
                {
                    Time = tuple.PostUpdatedAt,
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
            Func<(long Uid, TValue? Value, Time PostUpdatedAt), TRevision> revisionFactory)
            where TRevision : AuthorRevision where TPost : IPost
        {
            var existingRevisionOfExistingUsers = dbSet.AsNoTracking()
                .Where(e => e.Fid == db.Fid && posts.Select(p => p.AuthorUid).Contains(e.Uid))
                .Select(latestRevisionProjectionFactory)
                .Where(e => e.Rank == 1)
                .ToLinqToDB().ToList()
                .Join(posts, e => e.Uid, p => p.AuthorUid, (e, p) =>
                    (e.Uid, existing: e.Value, newInPost: postAuthorFieldValueSelector(p), PostUpdatedAt: p.UpdatedAt ?? p.CreatedAt))
                .ToList();
            var newRevisionOfNewUsers = posts
                // only required by IPost.AuthorManagerType since its nullable
                // since we shouldn't store so many nulls for users that mostly have no AuthorManagerType
                .Where(p => postAuthorFieldValueSelector(p) != null)
                .ExceptBy(existingRevisionOfExistingUsers.Select(tuple => tuple.Uid), p => p.AuthorUid)
                .Select(p => (Uid: p.AuthorUid, Value: postAuthorFieldValueSelector(p), PostUpdatedAt: p.UpdatedAt ?? p.CreatedAt));
            var newRevisionOfExistingUsers = existingRevisionOfExistingUsers
                .Where(tuple => isValueChangedPredicate(tuple.existing, tuple.newInPost))
                .Select(tuple => (tuple.Uid, Value: tuple.newInPost, tuple.PostUpdatedAt));
            lock (locks)
            {
                var newRevisionsExceptLocked = newRevisionOfNewUsers
                    .Concat(newRevisionOfExistingUsers)
                    .Select(revisionFactory)
                    .ExceptBy(locks, r => (r.Fid, r.Uid))
                    .ToList();
                if (!newRevisionsExceptLocked.Any()) return; // quick exit
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
