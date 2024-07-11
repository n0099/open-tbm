namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public partial class ReplySaver(
    ILogger<ReplySaver> logger,
    ConcurrentDictionary<PostId, ReplyPost> posts,
    ReplyContentImageSaver replyContentImageSaver,
    ReplySignatureSaver replySignatureSaver,
    AuthorRevisionSaver.New authorRevisionSaverFactory)
    : PostSaver<ReplyPost, BaseReplyRevision, Pid>(
        logger, posts, authorRevisionSaverFactory, PostType.Reply)
{
    public delegate ReplySaver New(ConcurrentDictionary<PostId, ReplyPost> posts);

    public override SaverChangeSet<ReplyPost> Save(CrawlerDbContext db)
    {
        var changeSet = Save(db, r => r.Pid,
            r => new ReplyRevision {TakenAt = r.UpdatedAt ?? r.CreatedAt, Pid = r.Pid},
            LinqKit.PredicateBuilder.New<ReplyPost>(r => Posts.Keys.Contains(r.Pid)));

        db.ReplyContents.AddRange(changeSet.NewlyAdded // https://github.com/dotnet/efcore/issues/33945
            .Select(r => new ReplyContent {Pid = r.Pid, ProtoBufBytes = r.Content}));
        PostSaveHandlers += replyContentImageSaver.Save(db, changeSet.NewlyAdded);
        PostSaveHandlers += AuthorRevisionSaver.SaveAuthorExpGradeRevisions(db, changeSet.AllAfter);
        PostSaveHandlers += replySignatureSaver.Save(db, changeSet.AllAfter);

        return changeSet;
    }
}
public partial class ReplySaver
{
    private Lazy<Dictionary<Type, AddSplitRevisionsDelegate>>? _addSplitRevisionsDelegatesKeyByEntityType;
    protected override Lazy<Dictionary<Type, AddSplitRevisionsDelegate>>
        AddSplitRevisionsDelegatesKeyByEntityType =>
        _addSplitRevisionsDelegatesKeyByEntityType ??= new(() => new()
        {
            {typeof(ReplyRevision.SplitFloor), AddRevisionsWithDuplicateIndex<ReplyRevision.SplitFloor>},
            {typeof(ReplyRevision.SplitSubReplyCount), AddRevisionsWithDuplicateIndex<ReplyRevision.SplitSubReplyCount>},
            {typeof(ReplyRevision.SplitAgreeCount), AddRevisionsWithDuplicateIndex<ReplyRevision.SplitAgreeCount>}
        });

    protected override Pid RevisionIdSelector(BaseReplyRevision entity) => entity.Pid;
    protected override Expression<Func<BaseReplyRevision, bool>>
        IsRevisionIdEqualsExpression(BaseReplyRevision newRevision) =>
        existingRevision => existingRevision.Pid == newRevision.Pid;
    protected override Expression<Func<BaseReplyRevision, RevisionIdWithDuplicateIndexProjection>>
        RevisionIdWithDuplicateIndexProjectionFactory() =>
        e => new() {RevisionId = e.Pid, DuplicateIndex = e.DuplicateIndex};
}
public partial class ReplySaver
{
    public override bool UserFieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => propName switch
    { // FansNickname in reply response will always be null
        nameof(User.FansNickname) when newValue is null && oldValue is not null => true,
        _ => false
    };
    public override bool UserFieldRevisionIgnorance(string propName, object? oldValue, object? newValue) => propName switch
    { // user icon will be null after UserParser.ResetUsersIcon() get invoked
        nameof(User.Icon) when newValue is not null && oldValue is null => true,
        _ => false
    };
    protected override bool FieldUpdateIgnorance
        (string propName, object? oldValue, object? newValue) => propName switch
    { // possible randomly respond with null
        nameof(ReplyPost.SignatureId) when newValue is null && oldValue is not null => true,
        _ => false
    };

    [SuppressMessage("StyleCop.CSharp.SpacingRules", "SA1025:Code should not contain multiple whitespace in a row")]
    protected override NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName) => fieldName switch
    {
        nameof(ReplyPost.IsFold)        => 1 << 2,
        nameof(ReplyPost.DisagreeCount) => 1 << 4,
        nameof(ReplyPost.Geolocation)   => 1 << 5,
        _ => 0
    };
}
