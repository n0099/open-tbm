namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public class SubReplySaver(
    ILogger<SubReplySaver> logger,
    ConcurrentDictionary<PostId, SubReplyPost> posts,
    AuthorRevisionSaver.New authorRevisionSaverFactory)
    : PostSaver<SubReplyPost, BaseSubReplyRevision, Spid>(
        logger, posts, authorRevisionSaverFactory, PostType.SubReply)
{
    public delegate SubReplySaver New(ConcurrentDictionary<PostId, SubReplyPost> posts);

    private Lazy<Dictionary<Type, AddSplitRevisionsDelegate>>? _addSplitRevisionsDelegatesKeyByEntityType;
    protected override Lazy<Dictionary<Type, AddSplitRevisionsDelegate>>
        AddSplitRevisionsDelegatesKeyByEntityType =>
        _addSplitRevisionsDelegatesKeyByEntityType ??= new(() => new()
        {
            {typeof(SubReplyRevision.SplitAgreeCount), AddSplitRevisions<SubReplyRevision.SplitAgreeCount>},
            {typeof(SubReplyRevision.SplitDisagreeCount), AddSplitRevisions<SubReplyRevision.SplitDisagreeCount>},
        });

    protected override Spid RevisionEntityIdSelector(BaseSubReplyRevision entity) => entity.Spid;
    protected override Expression<Func<BaseSubReplyRevision, bool>>
        IsRevisionEntityIdEqualsExpression(BaseSubReplyRevision newRevision) =>
        existingRevision => existingRevision.Spid == newRevision.Spid;

    public override bool UserFieldUpdateIgnorance
        (string propName, object? oldValue, object? newValue) => propName switch
    { // always ignore updates on iconinfo due to some rare user will show some extra icons
        // compare to reply response in the response of sub reply
        nameof(User.Icon) => true,

        // FansNickname in sub reply response will always be null
        nameof(User.FansNickname) when newValue is null && oldValue is not null => true,

        // DisplayName in users embedded in sub replies from response will be the legacy nickname
        nameof(User.DisplayName) => true,
        _ => false
    };

    public override SaverChangeSet<SubReplyPost> Save(CrawlerDbContext db)
    {
        var changeSet = Save(db, sr => sr.Spid,
            sr => new SubReplyRevision {TakenAt = sr.UpdatedAt ?? sr.CreatedAt, Spid = sr.Spid},
            LinqKit.PredicateBuilder.New<SubReplyPost>(sr => Posts.Keys.Contains(sr.Spid)));
        PostSaveHandlers += AuthorRevisionSaver.SaveAuthorExpGradeRevisions(db, changeSet.AllAfter).Invoke;

        return changeSet;
    }

    protected override NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName) => 0;
}
