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
            (TbmDbContext db, List<TPost> posts) where TPost : IPost
        {
            var existingRevisions = db.AuthorManagerTypeRevisions.AsNoTracking()
                .Where(r =>  r.Fid == db.Fid && posts.Select(i => i.AuthorUid).Contains(r.Uid))
                .Select(r => new
                {
                    r.Uid,
                    r.TriggeredBy,
                    r.AuthorManagerType,
                    Rank = Sql.Ext.Rank().Over().PartitionBy(r.Uid).OrderByDesc(r.Time).ToValue()
                })
                .Where(e => e.Rank <= 2)
                .ToLinqToDB().ToLookup(e => e.Uid);
            // prevent store so many nulls for mostly users that haven't AuthorManagerType, .ToList() did a shallow copy
            var filteredPosts = posts.Where(p => p.AuthorManagerType != null).ToList();
            if (typeof(TPost) == typeof(ThreadPost)) // the AuthorManagerType of sticky thread will always be null
                _ = (filteredPosts as List<ThreadPost>)?.RemoveAll(p => p.StickyType != null);
            _ = filteredPosts.RemoveAll(p =>
            {
                if (!existingRevisions.Contains(p.AuthorUid) || existingRevisions[p.AuthorUid].Count() == 1) return false;
                var first = existingRevisions[p.AuthorUid].First();
                var last = existingRevisions[p.AuthorUid].Last();
                var isFirstSame = p.AuthorManagerType == first.AuthorManagerType;
                var isLastSame = p.AuthorManagerType == last.AuthorManagerType;
                var isFirstNotSame = p.AuthorManagerType != first.AuthorManagerType;
                var isLastNotSame = p.AuthorManagerType != last.AuthorManagerType;
                // filter out posts that had encountered into a repeating pattern
                return (isFirstSame && isLastNotSame) || (isLastSame && isFirstNotSame);
            });
            SaveAuthorRevisions(db, filteredPosts, AuthorManagerTypeLocks,
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
                    Time = tuple.Time,
                    Fid = db.Fid,
                    Uid = tuple.Uid,
                    TriggeredBy = _triggeredByPostType,
                    AuthorManagerType = tuple.Value
                });
            return () => ReleaseAllLocks(AuthorManagerTypeLocks);
        }

        public Action SaveAuthorExpGradeRevisions<TPostWithAuthorExpGrade>
            (TbmDbContext db, ICollection<TPostWithAuthorExpGrade> posts)
            where TPostWithAuthorExpGrade : IPost, IPostWithAuthorExpGrade
        {
            SaveAuthorRevisions(db, posts, AuthorExpGradeLocks,
                db.AuthorExpGradeRevisions,
                p => p.AuthorExpGrade,
                (old, @new) =>
                { // randomly protoBuf default value 0 in reply and sub reply response
                    if (_triggeredByPostType is "reply" or "subReply" && @new == 0) return false;
                    return old != @new;
                },
                r => new()
                {
                    Uid = r.Uid,
                    Value = r.AuthorExpGrade,
                    Rank = Sql.Ext.Rank().Over().PartitionBy(r.Uid).OrderByDesc(r.Time).ToValue()
                },
                tuple => new()
                {
                    Time = tuple.Time,
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
            Func<(long Uid, TValue? Value, Time Time), TRevision> revisionFactory)
            where TRevision : AuthorRevision where TPost : IPost
        {
            var now = (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
            var existingRevisionOfExistingUsers = dbSet.AsNoTracking()
                .Where(e => e.Fid == db.Fid && posts.Select(p => p.AuthorUid).Distinct().Contains(e.Uid))
                .Select(latestRevisionProjectionFactory)
                .Where(e => e.Rank == 1)
                .ToLinqToDB().ToList()
                .Join(posts, e => e.Uid, p => p.AuthorUid, (e, p) =>
                    (e.Uid, existing: e.Value, newInPost: postAuthorFieldValueSelector(p), Time: now))
                .ToList();
            var newRevisionOfNewUsers = posts
                .ExceptBy(existingRevisionOfExistingUsers.Select(tuple => tuple.Uid), p => p.AuthorUid)
                .Select(p => (Uid: p.AuthorUid, Value: postAuthorFieldValueSelector(p), Time: now));
            var newRevisionOfExistingUsers = existingRevisionOfExistingUsers
                .Where(tuple => isValueChangedPredicate(tuple.existing, tuple.newInPost))
                .Select(tuple => (tuple.Uid, Value: tuple.newInPost, tuple.Time));
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
