using Uid = System.Int64;

namespace tbm.Crawler.Tieba.Crawl;

public partial class UserParserAndSaver
{
    protected override Dictionary<Type, RevisionUpsertDelegate>
        RevisionUpsertDelegatesKeyBySplitEntityType { get; } = new()
    {
        {
            typeof(UserRevision.SplitDisplayName), (db, revisions) =>
                db.Set<UserRevision.SplitDisplayName>()
                    .UpsertRange(revisions.OfType<UserRevision.SplitDisplayName>()).NoUpdate().Run()
        },
        {
            typeof(UserRevision.SplitPortraitUpdatedAt), (db, revisions) =>
                db.Set<UserRevision.SplitPortraitUpdatedAt>()
                    .UpsertRange(revisions.OfType<UserRevision.SplitPortraitUpdatedAt>()).NoUpdate().Run()
        },
        {
            typeof(UserRevision.SplitIpGeolocation), (db, revisions) =>
                db.Set<UserRevision.SplitIpGeolocation>()
                    .UpsertRange(revisions.OfType<UserRevision.SplitIpGeolocation>()).NoUpdate().Run()
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
public partial class UserParserAndSaver(ILogger<UserParserAndSaver> logger)
    : CommonInSavers<BaseUserRevision>(logger)
{
    private static readonly HashSet<Uid> UserIdLocks = [];
    private readonly List<Uid> _savedUsersId = [];
    private readonly ConcurrentDictionary<Uid, User> _users = new();

    public void ParseUsers(IEnumerable<TbClient.User> users) =>
        users.Select(el =>
        {
            static (string Portrait, uint? UpdateTime) ExtractPortrait(string portrait) =>
                ExtractPortraitRegex().Match(portrait) is {Success: true} m
                    ? (m.Groups["portrait"].Value, Time.Parse(m.Groups["timestamp"].ValueSpan, CultureInfo.InvariantCulture))
                    : (portrait, null);

            var uid = el.Uid;
            if (uid == 0) return null; // in client version 12.x the last user in list will be an empty user with uid 0
            var (portrait, portraitUpdatedAt) = ExtractPortrait(el.Portrait);
            if (uid < 0) // historical anonymous user
            {
                return new()
                {
                    Uid = uid,
                    Name = el.NameShow,
                    Portrait = portrait,
                    PortraitUpdatedAt = portraitUpdatedAt
                };
            }

            // will be an empty string when the user hasn't set a username for their baidu account yet
            var name = el.Name.NullIfEmpty();
            var nameShow = el.NameShow.NullIfEmpty();
            var u = new User();
            try
            {
                u.Uid = uid;
                u.Name = name;
                u.DisplayName = name == nameShow ? null : nameShow;
                u.Portrait = portrait;
                u.PortraitUpdatedAt = portraitUpdatedAt;
                u.Gender = (byte)el.Gender; // 0 when the user hasn't explicitly set their gender
                u.FansNickname = el.FansNickname.NullIfEmpty();
                u.Icon = Helper.SerializedProtoBufWrapperOrNullIfEmpty(el.Iconinfo,
                    () => new UserIconWrapper {Value = {el.Iconinfo}});
                u.IpGeolocation = el.IpAddress.NullIfEmpty();
                return u;
            }
            catch (Exception e)
            {
                e.Data["raw"] = Helper.UnescapedJsonSerialize(el);
                throw new InvalidDataException("User parse error.", e);
            }
        }).OfType<User>().ForEach(u => _users[u.Uid] = u);

    public void ResetUsersIcon() => _users.Values.ForEach(u => u.Icon = null);

    public void SaveUsers
        (CrawlerDbContext db, string postType, FieldChangeIgnoranceDelegates userFieldChangeIgnorance)
    {
        if (_users.IsEmpty) return;
        lock (UserIdLocks)
        {
            var usersExceptLocked = new Dictionary<Uid, User>(_users.ExceptBy(UserIdLocks, pair => pair.Key));
            if (usersExceptLocked.Count == 0) return;
            _savedUsersId.AddRange(usersExceptLocked.Keys);
            UserIdLocks.UnionWith(_savedUsersId);

            var existingUsersKeyByUid = (from user in db.Users.AsTracking().ForUpdate()
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

    public void PostSaveHook()
    {
        lock (UserIdLocks) if (_savedUsersId.Count != 0) UserIdLocks.ExceptWith(_savedUsersId);
    }

    [GeneratedRegex("^(?<portrait>.+)\\?t=(?<timestamp>[0-9]+)$", RegexOptions.Compiled, matchTimeoutMilliseconds: 100)]
    private static partial Regex ExtractPortraitRegex();
}
