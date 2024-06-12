using LinqKit;

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
        ExpressionStarter<TPost> existingPostPredicate)
        where TRevision : TBaseRevision
    {
        var existingPostsKeyById = db.Set<TPost>().AsTracking()
            .Where(existingPostPredicate).ToDictionary(postIdSelector);

        // deep copy before entities get mutated by SaverWithRevision.SaveEntitiesWithRevision()
        var existingBeforeMerge = existingPostsKeyById.Select(pair => (TPost)pair.Value.Clone()).ToList();

        SaveEntitiesWithRevision(db, revisionFactory,
            Posts.Values.ToLookup(p => existingPostsKeyById.ContainsKey(postIdSelector(p))),
            p => existingPostsKeyById[postIdSelector(p)]);
        return new(existingBeforeMerge, Posts.Values, postIdSelector);
    }
}
