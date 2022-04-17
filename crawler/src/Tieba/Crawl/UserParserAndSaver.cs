using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using Microsoft.Extensions.Logging;
using TbClient;

namespace tbm.Crawler
{
    public class UserParserAndSaver : CommonInSavers<UserParserAndSaver>
    {
        private readonly ILogger<UserParserAndSaver> _logger;
        private readonly ConcurrentDictionary<long, TiebaUser> _users = new();

        public UserParserAndSaver(ILogger<UserParserAndSaver> logger) => _logger = logger;

        public void ParseUsers(List<User> users) =>
            // if (!users.Any()) throw new TiebaException("User list is empty");
            users.Select(el =>
            {
                var rawUid = el.Id;
                // when thread's author user is anonymous, the first uid in user list will be empty string and this user will appears in next
                // if (rawUid == "") return null;
                var uid = (long)rawUid;
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
                    u.IconInfo = IParser<User, User>.RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.Iconinfo));
                    return u;
                }
                catch (Exception e)
                {
                    e.Data["rawJson"] = JsonSerializer.Serialize(el);
                    throw new Exception("User parse error", e);
                }
            }).ForEach(i => _users[i.Uid] = i);

        public void SaveUsers(TbmDbContext db)
        {
            var existingUsers = (from user in db.Users
                where _users.Keys.Any(uid => uid == user.Uid)
                select user).ToDictionary(i => i.Uid);
            SavePostsOrUsers(_logger, db, _users,
                u => existingUsers.ContainsKey(u.Uid),
                u => existingUsers[u.Uid],
                (now, u) => new UserRevision {Time = now, Uid = u.Uid});
        }
    }
}
