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
public partial class UserSaver(ILogger<UserSaver> logger, ConcurrentDictionary<Uid, User> users)
    : BaseSaver<BaseUserRevision>(logger)
{
    private static readonly HashSet<Uid> UserIdLocks = [];
    private readonly List<Uid> _savedUsersId = [];

    public delegate UserSaver New(ConcurrentDictionary<Uid, User> users);

    public void Save(
        CrawlerDbContext db,
        PostType postType,
        IFieldChangeIgnorance.FieldChangeIgnoranceDelegates userFieldChangeIgnorance)
    {
        if (users.IsEmpty) return;
        lock (UserIdLocks)
        {
            var usersExceptLocked = new Dictionary<Uid, User>(users.ExceptBy(UserIdLocks, pair => pair.Key));
            if (usersExceptLocked.Count == 0) return;
            _savedUsersId.AddRange(usersExceptLocked.Keys);
            UserIdLocks.UnionWith(_savedUsersId);

            var existingUsersKeyByUid = (from user in db.Users.AsTracking()
                where usersExceptLocked.Keys.Contains(user.Uid)
                select user).ToDictionary(u => u.Uid);
            SavePostsOrUsers(db, userFieldChangeIgnorance,
                u => new UserRevision
                {
                    TakenAt = u.UpdatedAt ?? u.CreatedAt,
                    Uid = u.Uid,
                    TriggeredBy = postType
                },
                usersExceptLocked.Values.ToLookup(u => existingUsersKeyByUid.ContainsKey(u.Uid)),
                u => existingUsersKeyByUid[u.Uid]);
        }
    }

    public IEnumerable<Uid> AcquireUidLocksForSave(IEnumerable<Uid> usersId)
    {
        lock (UserIdLocks)
        {
            var exceptLocked = usersId.Except(UserIdLocks).ToList();
            if (exceptLocked.Count == 0) return exceptLocked;
            _savedUsersId.AddRange(exceptLocked); // assume all given users are saved
            UserIdLocks.UnionWith(exceptLocked);
            return exceptLocked;
        }
    }

    public void OnPostSave()
    {
        lock (UserIdLocks) if (_savedUsersId.Count != 0) UserIdLocks.ExceptWith(_savedUsersId);
    }
}
