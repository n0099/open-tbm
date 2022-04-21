namespace tbm.Crawler
{
    public class UserParserAndSaver : CommonInSavers<UserParserAndSaver>
    {
        private readonly ILogger<UserParserAndSaver> _logger;
        private readonly ConcurrentDictionary<long, TiebaUser> _users = new();

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
                    return new TiebaUser
                    {
                        Uid = uid,
                        Name = el.NameShow,
                        AvatarUrl = el.Portrait
                    };
                }

                var name = el.Name.NullIfWhiteSpace(); // null when he haven't set username for his baidu account yet
                var nameShow = el.NameShow;
                var u = new TiebaUser();
                try
                {
                    u.Uid = uid;
                    u.Name = name;
                    u.DisplayName = name == nameShow ? null : nameShow;
                    u.AvatarUrl = el.Portrait;
                    u.Gender = (ushort)el.Gender; // null when he haven't explicitly set his gender
                    u.FansNickname = el.FansNickname.NullIfWhiteSpace();
                    u.IconInfo = Helper.SerializedProtoBufWrapperOrNullIfEmpty(() => new UserIconWrapper {Value = {el.Iconinfo}});
                    return u;
                }
                catch (Exception e)
                {
                    e.Data["rawJson"] = JsonSerializer.Serialize(el);
                    throw new Exception("User parse error", e);
                }
            }).OfType<TiebaUser>().ForEach(i => _users[i.Uid] = i);
        }

        public ILookup<bool, TiebaUser> SaveUsers(TbmDbContext db)
        { // IQueryable.ToList() works like AsEnumerable() which will eager eval the sql results from db
            var existingUsers = (from user in db.Users
                where _users.Keys.Any(uid => uid == user.Uid) select user).ToList();
            var existingUsersByUid = existingUsers.ToDictionary(i => i.Uid);
            var existingUsersBeforeSave = existingUsers.ToCloned(); // clone before it get updated by CommonInSavers.GetRevisionsForObjectsThenMerge()
            bool IsExistPredicate(TiebaUser u) => existingUsersByUid.ContainsKey(u.Uid);

            SavePostsOrUsers(_logger, db, _users, IsExistPredicate,
                u => existingUsersByUid[u.Uid],
                u => new UserRevision {Time = u.UpdatedAt, Uid = u.Uid});
            return existingUsersBeforeSave.ToLookup(IsExistPredicate);
        }
    }
}
