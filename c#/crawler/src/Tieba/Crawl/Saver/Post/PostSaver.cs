namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public abstract class PostSaver<TPostEntity, TParsedPost, TBaseRevision, TPostId>(
    ILogger<PostSaver<TPostEntity, TParsedPost, TBaseRevision, TPostId>> logger,
    ConcurrentDictionary<PostId, TParsedPost> posts,
    PostType currentPostType)
    : SaverWithRevision<TBaseRevision, TPostId>(logger), IPostSaver<TPostEntity, TParsedPost>
    where TPostEntity : RowVersionedEntity, IPost
    where TParsedPost : TPostEntity, IPost.IParsed
    where TBaseRevision : BaseRevisionWithSplitting
    where TPostId : struct
{
    public PostType CurrentPostType { get; } = currentPostType;
    protected ConcurrentDictionary<PostId, TParsedPost> Posts { get; } = posts;

    protected Action PostSaveHandlers { get; set; } = () => { };
    public void OnPostSave() => PostSaveHandlers();

    public virtual bool UserFieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => false;
    public virtual bool UserFieldRevisionIgnorance(string propName, object? oldValue, object? newValue) => false;

    public abstract SaverChangeSet<TPostEntity, TParsedPost> Save(CrawlerDbContext db);
    protected SaverChangeSet<TPostEntity, TParsedPost> Save<TRevision>(
        CrawlerDbContext db,
        Expression<Func<TPostEntity, PostId>> postIdSelectorExpression,
        Func<TPostEntity, TRevision> revisionFactory,
        Func<IQueryable<TPostEntity>, IQueryable<TPostEntity>> postQueryTransformer,
        Action<IEnumerable<MaybeExistingAndNewEntity<TPostEntity>>>? onBeforeSaveRevision = null)
        where TRevision : TBaseRevision
    {
        var postIdSelector = postIdSelectorExpression.Compile();
        var parsedPostIdSelector =
            ((Expression<Func<TParsedPost, PostId>>)new ReplaceParameterTypeVisitor<TPostEntity, TParsedPost>()
                .Visit(postIdSelectorExpression)).Compile();
        var existingPosts = postQueryTransformer(db.Set<TPostEntity>().AsTracking()).ToList();

        // clone before entities get mutated by SaverWithRevision.SaveEntitiesWithRevision()
        var existingPostsBeforeSave = existingPosts.Select(post => (TPostEntity)post.Clone()).ToList();
        var maybeExistingAndNewPosts = (from newPost in Posts.Values
            join existingPost in existingPosts
                on postIdSelector(newPost) equals postIdSelector(existingPost) into existingPostsWithSameId
            from existingPost in existingPostsWithSameId.DefaultIfEmpty()
            select new MaybeExistingAndNewEntity<TPostEntity>(existingPost, newPost)).ToList();

        var existingAndNewPosts = SaveNewEntities(db, maybeExistingAndNewPosts).ToList();
        SaveExistingEntities(db, existingAndNewPosts);
        onBeforeSaveRevision?.Invoke(maybeExistingAndNewPosts);
        SaveExistingEntityRevisions(db, revisionFactory, existingAndNewPosts);

        return new(postIdSelector, parsedPostIdSelector, Posts.Values, existingPostsBeforeSave, existingPosts);
    }
}
