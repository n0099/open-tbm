using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using Microsoft.Extensions.Logging;

namespace tbm.Crawler
{
    public class UserParser : CommonInPostAndUser
    {
        protected sealed override ILogger<object> Logger { get; init; }
        private readonly ConcurrentDictionary<long, TiebaUser> _users = new();

        public UserParser(ILogger<UserParser> logger) => Logger = logger;

        public void ParseUsers(IEnumerable<JsonElement> users)
        {
            var usersList = users.ToList();
            if (!usersList.Any()) throw new TiebaException("User list is empty");
            var newUsers = usersList.Select(u =>
            {
                var rawUid = u.GetStrProp("id");
                // when thread's author user is anonymous, the first uid in user list will be empty string and this user will appears in next
                if (rawUid == "") return null;
                var uid = long.Parse(rawUid);
                if (uid < 0) // historical anonymous user
                {
                    return new TiebaUser
                    {
                        Uid = uid,
                        Name = u.GetStrProp("name_show"),
                        AvatarUrl = u.GetStrProp("portrait")
                    };
                }
                var name = u.TryGetProperty("name", out var nameEl)
                    ? nameEl.GetString().NullIfWhiteSpace()
                    : null; // null when he haven't set username for his baidu account yet
                var nameShow = u.GetStrProp("name_show");
                return new TiebaUser
                {
                    Uid = uid,
                    Name = name,
                    DisplayName = name == nameShow ? null : nameShow,
                    AvatarUrl = u.GetStrProp("portrait"),
                    Gender = u.TryGetProperty("gender", out var genderEl)
                        ? ushort.TryParse(genderEl.GetString(), out var gender)
                            ? gender : null
                        : null, // null when he haven't explicitly set his gender
                    FansNickname = u.TryGetProperty("fans_nickname", out var fansNickName)
                        ? fansNickName.GetString().NullIfWhiteSpace()
                        : null,
                    IconInfo = RawJsonOrNullWhenEmpty(u.GetProperty("iconinfo"))
                };
            });
            // OfType() will remove null values
            newUsers.OfType<TiebaUser>().ForEach(i => _users[i.Uid] = i);
        }

        public void SaveUsers(TbmDbContext db)
        {
            var existingUsers = (from user in db.Users
                where _users.Keys.Any(uid => uid == user.Uid)
                select user).ToDictionary(i => i.Uid);
            SavePostsOrUsers(db, _users, TiebaUser.JsonTypeProps,
                u => existingUsers.ContainsKey(u.Uid),
                u => existingUsers[u.Uid],
                (now, u) => new UserRevision {Time = now, Uid = u.Uid});
        }
    }
}
