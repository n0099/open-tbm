using LinqKit;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public abstract class BaseSaver<TPost, TBaseRevision> : CommonInSavers<TBaseRevision>
    where TPost : class, IPost
    where TBaseRevision : class, IRevision
{
    public string PostType { get; }
    protected ConcurrentDictionary<ulong, TPost> Posts { get; }
    protected AuthorRevisionSaver AuthorRevisionSaver { get; }
    public virtual FieldChangeIgnoranceCallbacks TiebaUserFieldChangeIgnorance =>
        throw new NotImplementedException();

    public abstract SaverChangeSet<TPost> SavePosts(CrawlerDbContext db);

    protected delegate void PostSaveEventHandler();
    protected event PostSaveEventHandler PostSaveEvent = () => { };
    public void OnPostSaveEvent() => PostSaveEvent();

    protected BaseSaver(
        ILogger<BaseSaver<TPost, TBaseRevision>> logger,
        ConcurrentDictionary<PostId, TPost> posts,
        AuthorRevisionSaver.New authorRevisionSaverFactory,
        string postType
    ) : base(logger)
    {
        Posts = posts;
        AuthorRevisionSaver = authorRevisionSaverFactory(postType);
        PostType = postType;
    }

    protected SaverChangeSet<TPost> SavePosts<TRevision>(
        CrawlerDbContext db,
        Func<TPost, ulong> postIdSelector,
        Func<TPost, TRevision> revisionFactory,
        ExpressionStarter<TPost> existingPostPredicate
    )
        where TRevision : class, IRevision
    {
        var dbSet = db.Set<TPost>().TagWith("ForUpdate");
        if (dbSet == null) throw new ArgumentException(
            $"DbSet<{typeof(TPost).Name}> is not exist in DbContext.");

        var existingPostsKeyById = dbSet.Where(existingPostPredicate).ToDictionary(postIdSelector);
        // deep copy before entities get mutated by CommonInSavers.SavePostsOrUsers()
        var existingBeforeMerge = existingPostsKeyById.Select(pair => (TPost)pair.Value.Clone()).ToList();

        SavePostsOrUsers(db, TiebaUserFieldChangeIgnorance, revisionFactory,
            Posts.Values.ToLookup(p => existingPostsKeyById.ContainsKey(postIdSelector(p))),
            p => existingPostsKeyById[postIdSelector(p)]);
        return new(existingBeforeMerge, Posts.Values, postIdSelector);
    }
}
