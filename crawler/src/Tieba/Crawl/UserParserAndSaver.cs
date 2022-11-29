using System.Text.RegularExpressions;
using Uid = System.Int64;

namespace tbm.Crawler
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
            {nameof(TiebaUser.IconInfo),           1 << 5},
            {nameof(TiebaUser.IpGeolocation),      1 << 6}
        };
        private static readonly Dictionary<Type, string> TriggeredByPostSaverMap = new()
        {
            {typeof(ThreadSaver), "thread"},
            {typeof(ReplySaver), "reply"},
            {typeof(SubReplySaver), "subReply"}
        };
        private static readonly Regex PortraitExtractingRegex = new(@"^(.*?)\?t=(\d+)$", RegexOptions.Compiled, TimeSpan.FromSeconds(1));
        private static readonly HashSet<Uid> UsersIdLock = new();
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
                    u.IconInfo = Helper.SerializedProtoBufWrapperOrNullIfEmpty(() => new UserIconWrapper {Value = {el.Iconinfo}});
                    u.IpGeolocation = el.IpAddress.NullIfWhiteSpace();
                    return u;
                }
                catch (Exception e)
                {
                    e.Data["raw"] = Helper.UnescapedJsonSerialize(el);
                    throw new("User parse error.", e);
                }
            }).OfType<TiebaUser>().ForEach(u => _users[u.Uid] = u);

        public void SaveUsers<TPost>(TbmDbContext db, BaseSaver<TPost> postSaver) where TPost : class, IPost
        {
            if (_users.IsEmpty) return;
            lock (UsersIdLock)
            {
                var usersExceptLocked = _users.ExceptBy(UsersIdLock, i => i.Key).ToDictionary(i => i.Key, i => i.Value);
                if (!usersExceptLocked.Any()) return;
                _savedUsersId.AddRange(usersExceptLocked.Keys);
                UsersIdLock.UnionWith(_savedUsersId);
                var existingUsersKeyByUid = (from user in db.Users where usersExceptLocked.Keys.Contains(user.Uid) select user).ToDictionary(u => u.Uid);

                SavePostsOrUsers(postSaver.TiebaUserFieldChangeIgnorance, usersExceptLocked, db,
                    u => new UserRevision {Time = u.UpdatedAt ?? u.CreatedAt, Uid = u.Uid, TriggeredBy = TriggeredByPostSaverMap[postSaver.GetType()]},
                    u => existingUsersKeyByUid.ContainsKey(u.Uid),
                    u => existingUsersKeyByUid[u.Uid]);
            }
        }

        public IEnumerable<Uid> AcquireUidLock(IEnumerable<long> usersId)
        {
            lock (UsersIdLock)
            {
                var exceptLocked = usersId.Except(UsersIdLock).ToList();
                if (!exceptLocked.Any()) return exceptLocked;
                _savedUsersId.AddRange(exceptLocked);
                UsersIdLock.UnionWith(exceptLocked);
                return exceptLocked;
            }
        }

        public void PostSaveHook()
        {
            lock (UsersIdLock) if (_savedUsersId.Any()) UsersIdLock.ExceptWith(_savedUsersId);
        }
    }
}
