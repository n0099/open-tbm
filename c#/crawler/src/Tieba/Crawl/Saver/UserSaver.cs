using Microsoft.EntityFrameworkCore.ChangeTracking;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public partial class UserSaver
{
    private Lazy<Dictionary<Type, AddSplitRevisionsDelegate>>? _addSplitRevisionsDelegatesKeyByEntityType;
    protected override Lazy<Dictionary<Type, AddSplitRevisionsDelegate>>
        AddSplitRevisionsDelegatesKeyByEntityType =>
        _addSplitRevisionsDelegatesKeyByEntityType ??= new(() => new()
        {
            {typeof(UserRevision.SplitDisplayName), AddRevisionsWithDuplicateIndex<UserRevision.SplitDisplayName>},
            {typeof(UserRevision.SplitPortraitUpdatedAt), AddRevisionsWithDuplicateIndex<UserRevision.SplitPortraitUpdatedAt>},
            {typeof(UserRevision.SplitIpGeolocation), AddRevisionsWithDuplicateIndex<UserRevision.SplitIpGeolocation>}
        });

    protected override Uid RevisionEntityIdSelector(BaseUserRevision entity) => entity.Uid;
    protected override Expression<Func<BaseUserRevision, bool>>
        IsRevisionEntityIdEqualsExpression(BaseUserRevision newRevision) =>
        existingRevision => existingRevision.Uid == newRevision.Uid;

    protected override bool ShouldIgnoreEntityRevision(string propName, PropertyEntry propEntry, EntityEntry entityEntry)
    {
        // ThreadCrawlFacade.ParseLatestRepliers() will save users with empty string as portrait
        // they may soon be updated by (sub) reply crawler after it find out the latest reply
        // so we should ignore its revision update for all fields
        // ignore entire record is not possible via IFieldChangeIgnorance.GlobalFieldChangeIgnorance.Revision()
        // since it can only determine one field at the time
        if (propName != nameof(User.Portrait) || propEntry.OriginalValue is not "") return false;

        // invokes OriginalValues.ToObject() to get a new instance
        // since entityInTracking is reference to the changed one
        var user = (User)entityEntry.OriginalValues.ToObject();

        // create another user instance with only fields of latest replier filled
        var latestReplier = User.CreateLatestReplier(user.Uid, user.Name, user.DisplayName);

        // if they are same by fields values, the original one is the latest replier
        // that previously generated by ParseLatestRepliers()
        return User.EqualityComparer.Instance.Equals(user, latestReplier);
    }

    protected override bool FieldUpdateIgnorance
        (string propName, object? oldValue, object? newValue) => propName switch
    { // possible randomly respond with null
        nameof(User.IpGeolocation) when newValue is null => true,

        // possible clock drift across multiple response from tieba api
        // they should sync their servers with NTP
        /* following sql can track these drift
        SELECT portraitUpdatedAtDiff, COUNT(*), MAX(uid), MIN(uid), MAX(portraitUpdatedAt), MIN(portraitUpdatedAt)
        FROM (
            SELECT uid, portraitUpdatedAt, CAST(portraitUpdatedAt AS SIGNED)
                    - LEAD(CAST(portraitUpdatedAt AS SIGNED)) OVER (PARTITION BY uid ORDER BY time DESC) AS portraitUpdatedAtDiff
                FROM tbmcr_user WHERE portraitUpdatedAt IS NOT NULL
        ) AS T
        WHERE portraitUpdatedAtDiff > -100 AND portraitUpdatedAtDiff < 100
        GROUP BY portraitUpdatedAtDiff ORDER BY portraitUpdatedAtDiff;
        */
        nameof(User.PortraitUpdatedAt)
            when Math.Abs((newValue as int? ?? 0) - (oldValue as int? ?? 0)) <= 10 =>
            true,
        _ => false
    };

    protected override bool FieldRevisionIgnorance
        (string propName, object? oldValue, object? newValue) => propName switch
    { // ignore revision that figures update existing old users that don't have ip geolocation
        nameof(User.IpGeolocation) when oldValue is null => true,
        _ => false
    };

    [SuppressMessage("StyleCop.CSharp.SpacingRules", "SA1025:Code should not contain multiple whitespace in a row")]
    protected override NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName) => fieldName switch
    {
        nameof(User.Name)   => 1,
        nameof(User.Gender) => 1 << 3,
        nameof(User.Icon)   => 1 << 5,
        _ => 0
    };
}
public partial class UserSaver(
    ILogger<UserSaver> logger, SaverLocks<Uid> locks,
    IDictionary<Uid, User> users)
    : SaverWithRevision<BaseUserRevision, Uid>(logger)
{
    public delegate UserSaver New(IDictionary<Uid, User> users);
    public delegate bool FieldChangeIgnorance(string propName, object? oldValue, object? newValue);

    public void Save(
        CrawlerDbContext db,
        PostType postType,
        FieldChangeIgnorance userFieldUpdateIgnorance,
        FieldChangeIgnorance userFieldRevisionIgnorance)
    {
        if (users.Count == 0) return;
        var newlyLocked = locks.AcquireLocks(users.Keys().ToList());

        // existingUsers may have new revisions to insert so excluding already locked users
        // to prevent inserting duplicate revision
        var existingUsersKeyByUid = (from user in db.Users.AsTracking()
            where newlyLocked.Contains(user.Uid)
            select user).ToDictionary(u => u.Uid);
        SaveEntitiesWithRevision(db,
            u => new UserRevision
            {
                TakenAt = u.UpdatedAt ?? u.CreatedAt,
                Uid = u.Uid,
                TriggeredBy = postType
            },
            users.IntersectByKey(newlyLocked).Values()
                .ToLookup(u => existingUsersKeyByUid.ContainsKey(u.Uid)),
            u => existingUsersKeyByUid[u.Uid],
            userFieldUpdateIgnorance,
            userFieldRevisionIgnorance);
    }

    public IEnumerable<Uid> AcquireUidLocksForSave(IEnumerable<Uid> usersId) =>
        locks.AcquireLocks(usersId.ToList());

    [SuppressMessage("IDisposableAnalyzers.Correctness", "IDISP007:Don't dispose injected")]
    public void OnPostSave() => locks.Dispose();
}
