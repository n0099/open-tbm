using System.Linq.Expressions;

namespace tbm.Crawler.Tieba.Crawl.Saver
{
    public abstract class BaseSaver<TPost> : CommonInSavers<BaseSaver<TPost>> where TPost : class, IPost
    {
        public string PostType { get; }
        protected ConcurrentDictionary<ulong, TPost> Posts { get; }
        protected AuthorRevisionSaver AuthorRevisionSaver { get; }
        public virtual FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance => throw new NotImplementedException();

        public abstract SaverChangeSet<TPost> SavePosts(TbmDbContext db);

        protected virtual void PostSaveEventHandlerInternal() { }
        protected event PostSaveEventHandler PostSaveEvent = () => { };
        public void OnPostSaveEvent() => PostSaveEvent();
        protected delegate void PostSaveEventHandler();

        protected BaseSaver(ILogger<BaseSaver<TPost>> logger,
            ConcurrentDictionary<PostId, TPost> posts,
            AuthorRevisionSaver.New authorRevisionSaverFactory,
            string postType
        ) : base(logger)
        {
            Posts = posts;
            AuthorRevisionSaver = authorRevisionSaverFactory(postType);
            PostType = postType;
            PostSaveEvent += PostSaveEventHandlerInternal;
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

            var existingKeyById = dbSet.Where(existingPostPredicate).ToDictionary(postIdSelector);
            // shallow clone before entities get mutated by CommonInSavers.SavePostsOrUsers()
            var existingBeforeMerge = existingKeyById.Select(i => (TPost)i.Value.Clone()).ToList();

            SavePostsOrUsers(db, TiebaUserFieldChangeIgnorance, revisionFactory,
                Posts.Values.ToLookup(p => existingKeyById.ContainsKey(postIdSelector(p))),
                p => existingKeyById[postIdSelector(p)],
                revisionPostIdSelector, existingRevisionPredicate, revisionKeySelector);

            // tracking entities in existingPostsKeyById will have fields updated by TbmDbContext.TimestampingEntities()
            // this guarantee is currently required by nowhere
            var existingAfterTimestampingUnionNewlyAdded = existingKeyById
                .UnionBy(Posts, i => i.Key).Select(i => i.Value).ToList();
            PostSaveEvent += AuthorRevisionSaver.SaveAuthorManagerTypeRevisions(db, existingAfterTimestampingUnionNewlyAdded).Invoke;
            return new(existingBeforeMerge, existingAfterTimestampingUnionNewlyAdded, postIdSelector);
        }
    }
}
