namespace tbm.Crawler
{
    public abstract class BaseSaver<TPost> : CommonInSavers<BaseSaver<TPost>> where TPost : class, IPost
    {
        protected readonly ConcurrentDictionary<PostId, TPost> Posts;
        public virtual FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance => null!;

        public abstract SaverChangeSet<TPost> SavePosts(TbmDbContext db);
        public virtual void PostSaveCallback() {}

        protected BaseSaver(ILogger<BaseSaver<TPost>> logger, ConcurrentDictionary<PostId, TPost> posts) : base(logger) => Posts = posts;

        protected SaverChangeSet<TPost> SavePosts<TRevision>(
            TbmDbContext db,
            ExpressionStarter<TPost> postsPredicate,
            ExpressionStarter<PostIndex> indexPredicate,
            Func<TPost, PostId> postIdSelector,
            Expression<Func<PostIndex, PostId>> indexPostIdSelector,
            Func<TPost, PostIndex> indexFactory,
            Func<TPost, TRevision> revisionFactory)
            where TRevision : BaseRevision
        {
            var dbSet = db.Set<TPost>();
            if (dbSet == null) throw new ArgumentException($"DbSet<{typeof(TPost).Name}> is not exists in DbContext.");

            var existingPostsKeyById = dbSet.Where(postsPredicate).ToDictionary(postIdSelector);
            // shallow clone before entities get mutated by CommonInSavers.SavePostsOrUsers()
            var postsBeforeSave = existingPostsKeyById.Select(i => (TPost)i.Value.Clone()).ToList();

            SavePostsOrUsers(TiebaUserFieldChangeIgnorance, Posts, db, revisionFactory,
                p => existingPostsKeyById.ContainsKey(postIdSelector(p)),
                p => existingPostsKeyById[postIdSelector(p)]);
            var existingIndexPostId = db.PostsIndex.Where(indexPredicate).Select(indexPostIdSelector);
            db.AddRange(Posts.GetValuesByKeys(Posts.Keys.Except(existingIndexPostId)).Select(indexFactory));

            return new(postsBeforeSave, Posts.Values, postIdSelector);
        }
    }
}
