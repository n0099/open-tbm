using System.Linq.Expressions;
using LinqToDB;
using LinqToDB.EntityFrameworkCore;

namespace tbm.Crawler.Tieba.Crawl.Saver
{
    public abstract class BaseSaver<TPost> : CommonInSavers<BaseSaver<TPost>> where TPost : class, IPost
    {
        protected readonly ConcurrentDictionary<PostId, TPost> Posts;
        public virtual FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance => null!;

        public abstract SaverChangeSet<TPost> SavePosts(TbmDbContext db);
        public virtual void PostSaveHook() {}

        protected BaseSaver(ILogger<BaseSaver<TPost>> logger, ConcurrentDictionary<PostId, TPost> posts) : base(logger) => Posts = posts;

        protected SaverChangeSet<TPost> SavePosts<TRevision>(TbmDbContext db,
            Func<TPost, ulong> postIdSelector,
            Func<TRevision, long> revisionPostIdSelector,
            Func<TPost, TRevision> revisionFactory,
            ExpressionStarter<TPost> existingPostPredicate,
            Func<IEnumerable<TRevision>, Expression<Func<TRevision, bool>>> existingRevisionPredicate,
            Expression<Func<TRevision, TRevision>> revisionKeySelector)
            where TRevision : BaseRevision, new()
        {
            var dbSet = db.Set<TPost>();
            if (dbSet == null) throw new ArgumentException(
                $"DbSet<{typeof(TPost).Name}> is not exists in DbContext.");

            var existingPostsKeyById = dbSet.Where(existingPostPredicate).ToDictionary(postIdSelector);
            // shallow clone before entities get mutated by CommonInSavers.SavePostsOrUsers()
            var postsBeforeSave = existingPostsKeyById.Select(i => (TPost)i.Value.Clone()).ToList();

            SavePostsOrUsers(db, Posts, TiebaUserFieldChangeIgnorance, revisionFactory,
                p => existingPostsKeyById.ContainsKey(postIdSelector(p)),
                p => existingPostsKeyById[postIdSelector(p)],
                revisionPostIdSelector, existingRevisionPredicate, revisionKeySelector);
            SaveAuthorRevisions(db.Fid, db,
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
                    Time = (Time)DateTimeOffset.Now.ToUnixTimeSeconds(),
                    Fid = db.Fid,
                    Uid = tuple.Uid,
                    AuthorManagerType = tuple.Value
                });

            return new(postsBeforeSave, Posts.Values, postIdSelector);
        }

        protected class LatestAuthorRevisionProjection<TValue>
        {
            public long Uid { get; init; }
            public TValue? Value { get; init; }
            public long Rank { get; init; }
        }

        protected void SaveAuthorRevisions<TRevision, TValue>(Fid fid,
            TbmDbContext db,
            IQueryable<TRevision> dbSet,
            Func<TPost, TValue?> postAuthorFieldValueSelector,
            Func<TValue?, TValue?, bool> isValueChangedPredicate,
            Expression<Func<TRevision, LatestAuthorRevisionProjection<TValue>>> latestRevisionProjectionFactory,
            Func<(long Uid, TValue? Value), TRevision> revisionFactory)
            where TRevision : AuthorRevision
        {
            var existingRevisionOfExistingUsers = dbSet.AsNoTracking()
                .Where(e => e.Fid == fid && Posts.Values.Select(p => p.AuthorUid).Contains(e.Uid))
                .Select(latestRevisionProjectionFactory)
                .Where(e => e.Rank == 1)
                .ToLinqToDB().ToList()
                .Join(Posts.Values, e => e.Uid, p => p.AuthorUid,
                    (e, p) => (e.Uid, existing: e.Value, newInPost: postAuthorFieldValueSelector(p)))
                .ToList();
            var newRevisionOfNewUsers = Posts.Values
                .Where(p => postAuthorFieldValueSelector(p) != null)
                .ExceptBy(existingRevisionOfExistingUsers.Select(tuple => tuple.Uid), p => p.AuthorUid)
                .Select(p => (Uid: p.AuthorUid, Value: postAuthorFieldValueSelector(p)));
            var newRevisionOfExistingUsers = existingRevisionOfExistingUsers
                .Where(tuple => isValueChangedPredicate(tuple.existing, tuple.newInPost))
                .Select(tuple => (tuple.Uid, Value: tuple.newInPost));
            db.AddRange(newRevisionOfNewUsers.Concat(newRevisionOfExistingUsers).Select(revisionFactory));
        }
    }
}
