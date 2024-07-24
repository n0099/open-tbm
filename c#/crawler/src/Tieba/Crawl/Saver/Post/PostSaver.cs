namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public abstract class PostSaver<TPost, TBaseRevision, TPostId>(
    ILogger<PostSaver<TPost, TBaseRevision, TPostId>> logger,
    ConcurrentDictionary<PostId, TPost> posts,
    AuthorRevisionSaver.New authorRevisionSaverFactory,
    PostType currentPostType)
    : SaverWithRevision<TBaseRevision, TPostId>(logger), IPostSaver<TPost>
    where TPost : BasePost
    where TBaseRevision : BaseRevisionWithSplitting
    where TPostId : struct
{
    public PostType CurrentPostType { get; } = currentPostType;
    protected ConcurrentDictionary<PostId, TPost> Posts { get; } = posts;
    protected AuthorRevisionSaver AuthorRevisionSaver { get; } = authorRevisionSaverFactory(currentPostType);

    protected Action PostSaveHandlers { get; set; } = () => { };
    public void OnPostSave() => PostSaveHandlers();

    public virtual bool UserFieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => false;
    public virtual bool UserFieldRevisionIgnorance(string propName, object? oldValue, object? newValue) => false;

    public abstract SaverChangeSet<TPost> Save(CrawlerDbContext db);
    protected SaverChangeSet<TPost> Save<TRevision>(
        CrawlerDbContext db,
        Func<TPost, PostId> postIdSelector,
        Func<TPost, TRevision> revisionFactory,
        Func<IQueryable<TPost>, IQueryable<TPost>> postQueryTransformer,
        Action<IEnumerable<ExistingAndNewEntity<TPost>>>? onBeforeSaveRevision = null)
        where TRevision : TBaseRevision
    {
        var existingPosts = postQueryTransformer(db.Set<TPost>().AsTracking()).ToList();

        // clone before entities get mutated by SaverWithRevision.SaveEntitiesWithRevision()
        var existingPostsBeforeMerge = existingPosts.Select(post => (TPost)post.Clone()).ToList();
        var maybeExistingAndNewPosts = (from newPost in Posts.Values
            join existingPost in existingPosts
                on postIdSelector(newPost) equals postIdSelector(existingPost) into existingPostsWithSameId
            from existingPost in existingPostsWithSameId.DefaultIfEmpty()
            select new MaybeExistingAndNewEntity<TPost>(existingPost, newPost)).ToList();

        var existingAndNewPosts = SaveNewEntities(db, maybeExistingAndNewPosts).ToList();
        SaveExistingEntities(db, existingAndNewPosts);
        onBeforeSaveRevision?.Invoke(existingAndNewPosts);
        SaveExistingEntityRevisions(db, revisionFactory, existingAndNewPosts);
        return new(postIdSelector, existingPostsBeforeMerge, Posts.Values, existingPosts);
    }
}
