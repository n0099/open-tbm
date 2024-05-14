namespace tbm.Crawler.Tieba.Crawl.Saver;

public partial class UserSaver
{
    protected override Dictionary<Type, AddRevisionDelegate>
        AddRevisionDelegatesKeyBySplitEntityType { get; } = new()
    {
        {
            typeof(UserRevision.SplitDisplayName), (db, revisions) =>
                db.Set<UserRevision.SplitDisplayName>()
                    .AddRange(revisions.OfType<UserRevision.SplitDisplayName>())
        },
        {
            typeof(UserRevision.SplitPortraitUpdatedAt), (db, revisions) =>
                db.Set<UserRevision.SplitPortraitUpdatedAt>()
                    .AddRange(revisions.OfType<UserRevision.SplitPortraitUpdatedAt>())
        },
        {
            typeof(UserRevision.SplitIpGeolocation), (db, revisions) =>
                db.Set<UserRevision.SplitIpGeolocation>()
                    .AddRange(revisions.OfType<UserRevision.SplitIpGeolocation>())
        }
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
    ConcurrentDictionary<Uid, User> users)
    : BaseSaver<BaseUserRevision>(logger)
{
    public delegate UserSaver New(ConcurrentDictionary<Uid, User> users);

    public void Save(
        CrawlerDbContext db,
        PostType postType,
        IFieldChangeIgnorance.FieldChangeIgnoranceDelegates userFieldChangeIgnorance)
    {
        if (users.IsEmpty) return;
        locks.AcquireLocksThen(newlyLocked =>
            {
                var existingUsersKeyByUid = (from user in db.Users.AsTracking()
                    where newlyLocked.Select(u => u.Uid).Contains(user.Uid)
                    select user).ToDictionary(u => u.Uid);
                SavePostsOrUsers(db, userFieldChangeIgnorance,
                    u => new UserRevision
                    {
                        TakenAt = u.UpdatedAt ?? u.CreatedAt,
                        Uid = u.Uid,
                        TriggeredBy = postType
                    },
                    newlyLocked.ToLookup(u => existingUsersKeyByUid.ContainsKey(u.Uid)),
                    u => existingUsersKeyByUid[u.Uid]);
            },
            alreadyLocked => users
                .ExceptBy(alreadyLocked, pair => pair.Key).Select(pair => pair.Value).ToList(),
            newlyLocked => newlyLocked.Select(u => u.Uid));
    }

    public IEnumerable<Uid> AcquireUidLocksForSave(IEnumerable<Uid> usersId)
    {
        var exceptLocked = new List<Uid>();
        locks.AcquireLocksThen(
            newlyLocked => exceptLocked.AddRange(newlyLocked),
            alreadyLocked => usersId.Except(alreadyLocked).ToList(),
            i => i);
        return exceptLocked;
    }

    public void OnPostSave() => locks.ReleaseLocalLocked();
}
