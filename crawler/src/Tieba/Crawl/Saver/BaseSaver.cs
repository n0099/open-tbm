namespace tbm.Crawler
{
    public abstract class BaseSaver<TPost> : CommonInSavers<BaseSaver<TPost>> where TPost : class, IPost
    {
        private readonly ILogger<BaseSaver<TPost>> _logger;
        protected readonly ConcurrentDictionary<ulong, TPost> Posts;

        public abstract ILookup<bool, TPost> SavePosts(TbmDbContext db);

        protected BaseSaver(ILogger<BaseSaver<TPost>> logger, ConcurrentDictionary<ulong, TPost> posts)
        {
            _logger = logger;
            Posts = posts;
        }

        protected ILookup<bool, TPost> SavePosts<TPostRevision>(
            TbmDbContext db,
            ExpressionStarter<TPost> postsPredicate,
            ExpressionStarter<PostIndex> indexPredicate,
            Func<TPost, ulong> postIdSelector,
            Expression<Func<PostIndex, ulong>> indexPostIdSelector,
            Func<TPost, PostIndex> indexFactory,
            Func<TPost, TPostRevision> revisionFactory)
        {
            var dbSet = db.Set<TPost>();
            if (dbSet == null) throw new ArgumentException($"DbSet<{typeof(TPost).Name}> is not exists in DbContext");

            // IQueryable.ToList() works like AsEnumerable() which will eager eval the sql results from db
            var existingPosts = dbSet.Where(postsPredicate).ToList();
            var existingPostsById = existingPosts.ToDictionary(postIdSelector);
            var postsBeforeSave = existingPosts.ToCloned(); // clone before it get updated by CommonInSavers.GetRevisionsForObjectsThenMerge()
            bool IsExistPredicate(TPost p) => existingPostsById.ContainsKey(postIdSelector(p));

            SavePostsOrUsers(_logger, db, Posts, IsExistPredicate,
                p => existingPostsById[postIdSelector(p)], revisionFactory);
            var existingIndexPostId = db.PostsIndex.Where(indexPredicate).Select(indexPostIdSelector);
            db.AddRange(Posts.GetValuesByKeys(Posts.Keys.Except(existingIndexPostId)).Select(indexFactory));

            postsBeforeSave.AddRange(Posts.Values);
            return postsBeforeSave.ToLookup(IsExistPredicate);
        }
    }
}
