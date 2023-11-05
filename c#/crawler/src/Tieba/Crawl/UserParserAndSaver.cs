using Uid = System.Int64;

namespace tbm.Crawler.Tieba.Crawl;

public partial class UserParserAndSaver(ILogger<UserParserAndSaver> logger)
    : CommonInSavers<BaseUserRevision>(logger)
{
    protected override ushort GetRevisionNullFieldBitMask(string fieldName) => fieldName switch
    {
        nameof(TiebaUser.Name)   => 1,
        nameof(TiebaUser.Gender) => 1 << 3,
        nameof(TiebaUser.Icon)   => 1 << 5,
        _ => 0
    };

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

    [GeneratedRegex("^(.+)\\?t=([0-9]+)$", RegexOptions.Compiled, matchTimeoutMilliseconds: 100)]
    private static partial Regex ExtractPortraitRegex();

    private static readonly HashSet<Uid> UserIdLocks = new();
    private readonly List<Uid> _savedUsersId = new();
    private readonly ConcurrentDictionary<Uid, TiebaUser> _users = new();

    public void ParseUsers(IEnumerable<User> users) =>
        users.Select(el =>
        {
            static (string Portrait, uint? UpdateTime) ExtractPortrait(string portrait) =>
                ExtractPortraitRegex().Match(portrait) is {Success: true} m
                    ? (m.Groups[1].Value, Time.Parse(m.Groups[2].ValueSpan))
                    : (portrait, null);

            var uid = el.Uid;
            if (uid == 0) return null; // in client version 12.x the last user in list will be a empty user with uid 0
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
            var u = new TiebaUser();
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
                throw new("User parse error.", e);
            }
        }).OfType<TiebaUser>().ForEach(u => _users[u.Uid] = u);

    public void ResetUsersIcon() => _users.Values.ForEach(u => u.Icon = null);

    public void SaveUsers(CrawlerDbContext db, string postType, FieldChangeIgnoranceDelegates tiebaUserFieldChangeIgnorance)
    {
        if (_users.IsEmpty) return;
        lock (UserIdLocks)
        {
            var usersExceptLocked = new Dictionary<Uid, TiebaUser>(_users.ExceptBy(UserIdLocks, pair => pair.Key));
            if (!usersExceptLocked.Any()) return;
            _savedUsersId.AddRange(usersExceptLocked.Keys);
            UserIdLocks.UnionWith(_savedUsersId);

            var existingUsersKeyByUid = (from user in db.Users.AsTracking().ForUpdate()
                where usersExceptLocked.Keys.Contains(user.Uid)
                select user).ToDictionary(u => u.Uid);
            SavePostsOrUsers(db, tiebaUserFieldChangeIgnorance,
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
            if (!exceptLocked.Any()) return exceptLocked;
            _savedUsersId.AddRange(exceptLocked); // assume all given users are saved
            UserIdLocks.UnionWith(exceptLocked);
            return exceptLocked;
        }
    }

    public void PostSaveHook()
    {
        lock (UserIdLocks) if (_savedUsersId.Any()) UserIdLocks.ExceptWith(_savedUsersId);
    }
}
