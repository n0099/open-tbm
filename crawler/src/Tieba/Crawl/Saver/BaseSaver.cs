namespace tbm.Crawler
{
    public abstract class BaseSaver<TPost> : CommonInSavers<BaseSaver<TPost>> where TPost : class, IPost
    {
        private readonly ILogger<BaseSaver<TPost>> _logger;
        protected readonly ConcurrentDictionary<PostId, TPost> Posts;
        public virtual FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance => null!;

        public abstract SaverChangeSet<TPost> SavePosts(TbmDbContext db);
        public virtual void PostSaveCallback() {}

        protected BaseSaver(ILogger<BaseSaver<TPost>> logger, ConcurrentDictionary<PostId, TPost> posts)
        {
            _logger = logger;
            Posts = posts;
        }

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

            // IQueryable.ToList() works like AsEnumerable() which will eager eval the sql results from db
            var existingPosts = dbSet.Where(postsPredicate).ToList();
            var existingPostsById = existingPosts.ToDictionary(postIdSelector);
            var postsBeforeSave = existingPosts.ToCloned(); // clone before it get updated by CommonInSavers.GetRevisionsForObjectsThenMerge()

            SavePostsOrUsers(_logger, TiebaUserFieldChangeIgnorance, Posts, db, revisionFactory,
                p => existingPostsById.ContainsKey(postIdSelector(p)),
                p => existingPostsById[postIdSelector(p)]);
            var existingIndexPostId = db.PostsIndex.Where(indexPredicate).Select(indexPostIdSelector);
            db.AddRange(Posts.GetValuesByKeys(Posts.Keys.Except(existingIndexPostId)).Select(indexFactory));

            return new(postsBeforeSave, Posts.Values, postIdSelector);
        }
    }
}
