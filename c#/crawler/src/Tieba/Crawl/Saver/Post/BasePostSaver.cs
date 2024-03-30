using LinqKit;

namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public abstract class BasePostSaver<TPost, TBaseRevision>(
        ILogger<BasePostSaver<TPost, TBaseRevision>> logger,
        ConcurrentDictionary<PostId, TPost> posts,
        AuthorRevisionSaver.New authorRevisionSaverFactory,
        string postType)
    : BaseSaver<TBaseRevision>(logger)
    where TPost : class, IPost
    where TBaseRevision : class, IRevision
{
    protected delegate void PostSaveEventHandler();
    [SuppressMessage("Design", "MA0046:Use EventHandler<T> to declare events")]
    protected event PostSaveEventHandler PostSaveEvent = () => { };

    public virtual IFieldChangeIgnorance.FieldChangeIgnoranceDelegates
        UserFieldChangeIgnorance => throw new NotSupportedException();
    public string PostType { get; } = postType;
    protected ConcurrentDictionary<PostId, TPost> Posts { get; } = posts;
    protected AuthorRevisionSaver AuthorRevisionSaver { get; } = authorRevisionSaverFactory(postType);

    public void OnPostSaveEvent() => PostSaveEvent();
    public abstract SaverChangeSet<TPost> Save(CrawlerDbContext db);

    protected SaverChangeSet<TPost> Save<TRevision>(
        CrawlerDbContext db,
        Func<TPost, PostId> postIdSelector,
        Func<TPost, TRevision> revisionFactory,
        ExpressionStarter<TPost> existingPostPredicate)
        where TRevision : class, IRevision
    {
        var dbSet = db.Set<TPost>().ForUpdate();

        var existingPostsKeyById = dbSet.Where(existingPostPredicate).ToDictionary(postIdSelector);

        // deep copy before entities get mutated by BaseSaver.SavePostsOrUsers()
        var existingBeforeMerge = existingPostsKeyById.Select(pair => (TPost)pair.Value.Clone()).ToList();

        SavePostsOrUsers(db, UserFieldChangeIgnorance, revisionFactory,
            Posts.Values.ToLookup(p => existingPostsKeyById.ContainsKey(postIdSelector(p))),
            p => existingPostsKeyById[postIdSelector(p)]);
        return new(existingBeforeMerge, Posts.Values, postIdSelector);
    }
}
