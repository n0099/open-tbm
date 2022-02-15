using System;
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
            var newUsers = usersList.Select(el =>
            {
                var rawUid = el.GetStrProp("id");
                // when thread's author user is anonymous, the first uid in user list will be empty string and this user will appears in next
                if (rawUid == "") return null;
                var uid = long.Parse(rawUid);
                if (uid < 0) // historical anonymous user
                {
                    return new TiebaUser
                    {
                        Uid = uid,
                        Name = el.GetStrProp("name_show"),
                        AvatarUrl = el.GetStrProp("portrait")
                    };
                }
                var name = el.TryGetProperty("name", out var nameEl)
                    ? nameEl.GetString().NullIfWhiteSpace()
                    : null; // null when he haven't set username for his baidu account yet
                var nameShow = el.GetStrProp("name_show");

                var u = new TiebaUser();
                try
                {
                    u.Uid = uid;
                    u.Name = name;
                    u.DisplayName = name == nameShow ? null : nameShow;
                    u.AvatarUrl = el.GetStrProp("portrait");
                    u.Gender = el.TryGetProperty("gender", out var genderEl)
                        ? ushort.TryParse(genderEl.GetString(), out var gender)
                            ? gender : null
                        : null; // null when he haven't explicitly set his gender
                    u.FansNickname = el.TryGetProperty("fans_nickname", out var fansNickName)
                        ? fansNickName.GetString().NullIfWhiteSpace()
                        : null;
                    u.IconInfo = RawJsonOrNullWhenEmpty(el.GetProperty("iconinfo"));
                    return u;
                }
                catch (Exception e)
                {
                    e.Data["rawJson"] = el.GetRawText();
                    throw new Exception("User parse error", e);
                }
            });
            // OfType() will remove null values
            newUsers.OfType<TiebaUser>().ForEach(i => _users[i.Uid] = i);
        }

        public void SaveUsers(TbmDbContext db)
        {
            var existingUsers = (from user in db.Users
                where _users.Keys.Any(uid => uid == user.Uid)
                select user).ToDictionary(i => i.Uid);
            SavePostsOrUsers(db, _users,
                u => existingUsers.ContainsKey(u.Uid),
                u => existingUsers[u.Uid],
                (now, u) => new UserRevision {Time = now, Uid = u.Uid});
        }
    }
}
