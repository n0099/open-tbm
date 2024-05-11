using LinqKit;

namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public abstract class PostSaver<TPost, TBaseRevision>(
        ILogger<PostSaver<TPost, TBaseRevision>> logger,
        ConcurrentDictionary<PostId, TPost> posts,
        AuthorRevisionSaver.New authorRevisionSaverFactory,
        PostType currentPostType)
    : BaseSaver<TBaseRevision>(logger), IPostSaver<TPost>
    where TPost : BasePost
    where TBaseRevision : BaseRevisionWithSplitting
{
    protected delegate void PostSaveHandler();

    public virtual IFieldChangeIgnorance.FieldChangeIgnoranceDelegates
        UserFieldChangeIgnorance => throw new NotSupportedException();
    public PostType CurrentPostType { get; } = currentPostType;
    protected ConcurrentDictionary<PostId, TPost> Posts { get; } = posts;
    protected AuthorRevisionSaver AuthorRevisionSaver { get; } = authorRevisionSaverFactory(currentPostType);

    protected PostSaveHandler PostSaveHandlers { get; set; } = () => { };
    public void OnPostSave() => PostSaveHandlers();

    public abstract SaverChangeSet<TPost> Save(CrawlerDbContext db);
    protected SaverChangeSet<TPost> Save<TRevision>(
        CrawlerDbContext db,
        Func<TPost, PostId> postIdSelector,
        Func<TPost, TRevision> revisionFactory,
        ExpressionStarter<TPost> existingPostPredicate)
        where TRevision : BaseRevisionWithSplitting
    {
        var existingPostsKeyById = db.Set<TPost>()
            .Where(existingPostPredicate).ToDictionary(postIdSelector);

        // deep copy before entities get mutated by BaseSaver.SavePostsOrUsers()
        var existingBeforeMerge = existingPostsKeyById.Select(pair => (TPost)pair.Value.Clone()).ToList();

        SavePostsOrUsers(db, UserFieldChangeIgnorance, revisionFactory,
            Posts.Values.ToLookup(p => existingPostsKeyById.ContainsKey(postIdSelector(p))),
            p => existingPostsKeyById[postIdSelector(p)]);
        return new(existingBeforeMerge, Posts.Values, postIdSelector);
    }
}
