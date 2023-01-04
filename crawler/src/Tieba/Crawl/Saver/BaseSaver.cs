using System.Linq.Expressions;

namespace tbm.Crawler.Tieba.Crawl.Saver
{
    public abstract class BaseSaver<TPost> : CommonInSavers<BaseSaver<TPost>> where TPost : class, IPost
    {
        protected readonly ConcurrentDictionary<PostId, TPost> Posts;
        protected readonly AuthorRevisionSaver AuthorRevisionSaver;
        public virtual FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance => null!;

        public abstract SaverChangeSet<TPost> SavePosts(TbmDbContext db);
        public virtual void PostSaveHook() {}

        protected BaseSaver(ILogger<BaseSaver<TPost>> logger,
            ConcurrentDictionary<PostId, TPost> posts,
            AuthorRevisionSaver authorRevisionSaver) : base(logger)
        {
            Posts = posts;
            AuthorRevisionSaver = authorRevisionSaver;
        }

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
            AuthorRevisionSaver.SaveAuthorManagerTypeRevisions(db, Posts.Values);

            return new(postsBeforeSave, Posts.Values, postIdSelector);
        }

    }
}
