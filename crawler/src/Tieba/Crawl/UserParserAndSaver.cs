using System.Text.RegularExpressions;
using Uid = System.Int64;

namespace tbm.Crawler.Tieba.Crawl
{
    public class UserParserAndSaver : CommonInSavers<UserParserAndSaver>
    {
        protected override Dictionary<string, ushort> RevisionNullFieldsBitMasks { get; } = new()
        {
            {nameof(TiebaUser.Name),               1},
            {nameof(TiebaUser.DisplayName),        1 << 1},
            {nameof(TiebaUser.PortraitUpdateTime), 1 << 2},
            {nameof(TiebaUser.Gender),             1 << 3},
            {nameof(TiebaUser.FansNickname),       1 << 4},
            {nameof(TiebaUser.Icon),               1 << 5},
            {nameof(TiebaUser.IpGeolocation),      1 << 6}
        };
        private static readonly Regex PortraitExtractingRegex =
            new(@"^(.*?)\?t=(\d+)$", RegexOptions.Compiled, TimeSpan.FromSeconds(1));
        private static readonly HashSet<Uid> UserIdLocks = new();
        private readonly List<Uid> _savedUsersId = new();
        private readonly ConcurrentDictionary<Uid, TiebaUser> _users = new();

        public UserParserAndSaver(ILogger<UserParserAndSaver> logger) : base(logger) { }

        public void ParseUsers(IEnumerable<User> users) =>
            users.Select(el =>
            {
                static (string Portrait, uint? UpdateTime) ExtractPortrait(string portrait) =>
                    PortraitExtractingRegex.Match(portrait) is {Success: true} m
                        ? (m.Groups[1].Value, Time.Parse(m.Groups[2].ValueSpan)) : (portrait, null);

                var uid = el.Uid;
                if (uid == 0) return null; // in client version 12.x the last user in list will be a empty user with uid 0
                var (portrait, portraitUpdateTime) = ExtractPortrait(el.Portrait);
                if (uid < 0) // historical anonymous user
                {
                    return new()
                    {
                        Uid = uid,
                        Name = el.NameShow,
                        Portrait = portrait,
                        PortraitUpdateTime = portraitUpdateTime
                    };
                }

                var name = el.Name.NullIfWhiteSpace(); // null when he haven't set username for his baidu account yet
                var nameShow = el.NameShow.NullIfWhiteSpace();
                var u = new TiebaUser();
                try
                {
                    u.Uid = uid;
                    u.Name = name;
                    u.DisplayName = name == nameShow ? null : nameShow;
                    u.Portrait = portrait;
                    u.PortraitUpdateTime = portraitUpdateTime;
                    u.Gender = (ushort)el.Gender; // 0 when he haven't explicitly set his gender
                    u.FansNickname = el.FansNickname.NullIfWhiteSpace();
                    u.Icon = Helper.SerializedProtoBufWrapperOrNullIfEmpty(el.Iconinfo,
                        () => new UserIconWrapper {Value = {el.Iconinfo}});
                    u.IpGeolocation = el.IpAddress.NullIfWhiteSpace();
                    return u;
                }
                catch (Exception e)
                {
                    e.Data["raw"] = Helper.UnescapedJsonSerialize(el);
                    throw new("User parse error.", e);
                }
            }).OfType<TiebaUser>().ForEach(u => _users[u.Uid] = u);

        public void ResetUsersIcon() => _users.Values.ForEach(u => u.Icon = null);

        public void SaveUsers(TbmDbContext db, string postType,
            FieldChangeIgnoranceCallbackRecord tiebaUserFieldChangeIgnorance)
        {
            if (_users.IsEmpty) return;
            lock (UserIdLocks)
            {
                var usersExceptLocked = _users.ExceptBy(UserIdLocks, i => i.Key).ToDictionary(i => i.Key, i => i.Value);
                if (!usersExceptLocked.Any()) return;
                _savedUsersId.AddRange(usersExceptLocked.Keys);
                UserIdLocks.UnionWith(_savedUsersId);

                var existingUsersKeyByUid = (from user in db.Users.AsTracking()
                    where usersExceptLocked.Keys.Contains(user.Uid)
                    select user).ToDictionary(u => u.Uid);
                SavePostsOrUsers(db, tiebaUserFieldChangeIgnorance,
                    u => new UserRevision
                    {
                        Time = u.UpdatedAt ?? u.CreatedAt,
                        Uid = u.Uid,
                        TriggeredBy = postType
                    },
                    usersExceptLocked.Values.ToLookup(u => existingUsersKeyByUid.ContainsKey(u.Uid)),
                    u => existingUsersKeyByUid[u.Uid],
                    r => r.Uid,
                    newRevisions => existing => newRevisions.Select(r => r.Uid).Contains(existing.Uid),
                    r => new() {Time = r.Time, Uid = r.Uid});
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
}
