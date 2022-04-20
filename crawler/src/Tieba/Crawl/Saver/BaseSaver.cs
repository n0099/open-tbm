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
            if (dbSet == null) throw new ArgumentException("DbSet<TPost> is not exists in DbContext");

            var existingPosts = dbSet.Where(postsPredicate).ToDictionary(postIdSelector);
            var existingOrNewPosts = SavePostsOrUsers(_logger, db, Posts,
                p => existingPosts.ContainsKey(postIdSelector(p)),
                p => existingPosts[postIdSelector(p)],
                revisionFactory);

            var existingIndexPostId = db.PostsIndex.Where(indexPredicate).Select(indexPostIdSelector);
            db.AddRange(Posts.GetValuesByKeys(Posts.Keys.Except(existingIndexPostId)).Select(indexFactory));
            return existingOrNewPosts;
        }
    }
}
