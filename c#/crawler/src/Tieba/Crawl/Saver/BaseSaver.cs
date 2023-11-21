using LinqKit;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public abstract class BaseSaver<TPost, TBaseRevision>(
        ILogger<BaseSaver<TPost, TBaseRevision>> logger,
        ConcurrentDictionary<PostId, TPost> posts,
        AuthorRevisionSaver.New authorRevisionSaverFactory,
        string postType)
    : CommonInSavers<TBaseRevision>(logger)
    where TPost : class, IPost
    where TBaseRevision : class, IRevision
{
    protected delegate void PostSaveEventHandler();
    protected event PostSaveEventHandler PostSaveEvent = () => { };

    public virtual FieldChangeIgnoranceDelegates TiebaUserFieldChangeIgnorance =>
        throw new NotImplementedException();
    public string PostType { get; } = postType;
    protected ConcurrentDictionary<ulong, TPost> Posts { get; } = posts;
    protected AuthorRevisionSaver AuthorRevisionSaver { get; } = authorRevisionSaverFactory(postType);

    public void OnPostSaveEvent() => PostSaveEvent();
    public abstract SaverChangeSet<TPost> SavePosts(CrawlerDbContext db);

    protected SaverChangeSet<TPost> SavePosts<TRevision>(
        CrawlerDbContext db,
        Func<TPost, ulong> postIdSelector,
        Func<TPost, TRevision> revisionFactory,
        ExpressionStarter<TPost> existingPostPredicate)
        where TRevision : class, IRevision
    {
        var dbSet = db.Set<TPost>().ForUpdate();

        var existingPostsKeyById = dbSet.Where(existingPostPredicate).ToDictionary(postIdSelector);

        // deep copy before entities get mutated by CommonInSavers.SavePostsOrUsers()
        var existingBeforeMerge = existingPostsKeyById.Select(pair => (TPost)pair.Value.Clone()).ToList();

        SavePostsOrUsers(db, TiebaUserFieldChangeIgnorance, revisionFactory,
            Posts.Values.ToLookup(p => existingPostsKeyById.ContainsKey(postIdSelector(p))),
            p => existingPostsKeyById[postIdSelector(p)]);
        return new(existingBeforeMerge, Posts.Values, postIdSelector);
    }
}
