using Uid = System.Int64;

namespace tbm.Crawler
{
    public class UserParserAndSaver : CommonInSavers<UserParserAndSaver>
    {
        private static readonly Dictionary<Type, string> TriggeredByPostSaverMap = new()
        {
            {typeof(ThreadSaver), "thread"},
            {typeof(ReplySaver), "reply"},
            {typeof(SubReplySaver), "subReply"}
        };
        private static readonly HashSet<Uid> UidLock = new();
        private readonly ILogger<UserParserAndSaver> _logger;
        private readonly ConcurrentDictionary<Uid, TiebaUser> _users = new();

        public UserParserAndSaver(ILogger<UserParserAndSaver> logger) => _logger = logger;

        public void ParseUsers(IList<User> users)
        {
            if (!users.Any()) throw new TiebaException("User list is empty");
            users.Select(el =>
            {
                var uid = el.Uid;
                if (uid == 0) return null; // in client version 12.x the last user in list will be a empty user with uid 0
                if (uid < 0) // historical anonymous user
                {
                    return new()
                    {
                        Uid = uid,
                        Name = el.NameShow,
                        AvatarUrl = el.Portrait
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
                    u.AvatarUrl = el.Portrait;
                    u.Gender = (ushort)el.Gender; // 0 when he haven't explicitly set his gender
                    u.FansNickname = el.FansNickname.NullIfWhiteSpace();
                    u.IconInfo = Helper.SerializedProtoBufWrapperOrNullIfEmpty(() => new UserIconWrapper {Value = {el.Iconinfo}});
                    return u;
                }
                catch (Exception e)
                {
                    e.Data["raw"] = Helper.UnescapedJsonSerialize(el);
                    throw new("User parse error", e);
                }
            }).OfType<TiebaUser>().ForEach(i => _users[i.Uid] = i);
        }

        public IEnumerable<Uid>? SaveUsers<TPost>(TbmDbContext db, BaseSaver<TPost> postSaver) where TPost : class, IPost
        {
            if (_users.IsEmpty) return null;
            lock (UidLock)
            {
                var usersExceptLocked = _users.ExceptBy(UidLock, u => u.Key).ToDictionary(i => i.Key, i => i.Value);
                if (usersExceptLocked.Count == 0) return null;
                UidLock.UnionWith(usersExceptLocked.Keys);
                // IQueryable.ToList() works like AsEnumerable() which will eager eval the sql results from db
                var existingUsers = (from user in db.Users where usersExceptLocked.Keys.Any(uid => uid == user.Uid) select user).ToList();
                var existingUsersByUid = existingUsers.ToDictionary(i => i.Uid);

                SavePostsOrUsers(_logger, postSaver.TiebaUserFieldChangeIgnorance, usersExceptLocked, db,
                    u => new UserRevision {Time = u.UpdatedAt, Uid = u.Uid, TriggeredBy = TriggeredByPostSaverMap[postSaver.GetType()]},
                    () => new UserRevisionNullFields(),
                    u => existingUsersByUid.ContainsKey(u.Uid),
                    u => existingUsersByUid[u.Uid]);
                return usersExceptLocked.Keys.ToList();
            }
        }

        public static void ReleaseLocks(IEnumerable<Uid> usersId)
        {
            lock (UidLock) UidLock.ExceptWith(usersId);
        }
    }
}
