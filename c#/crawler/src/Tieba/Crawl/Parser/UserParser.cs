namespace tbm.Crawler.Tieba.Crawl.Parser;

public partial class UserParser(ConcurrentDictionary<Uid, User.Parsed> users)
{
    public delegate UserParser New(ConcurrentDictionary<Uid, User.Parsed> users);

    public void Parse(IEnumerable<TbClient.User> inUsers) =>
        inUsers.Select(el =>
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
                    Name = el.NameShow.NullIfEmpty(),
                    Portrait = portrait,
                    PortraitUpdatedAt = portraitUpdatedAt
                };
            }

            // will be an empty string when the user hasn't set a username for their baidu account yet
            var name = el.Name.NullIfEmpty();
            var nameShow = el.NameShow.NullIfEmpty();
            var u = new User.Parsed {Portrait = ""};
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
                    value => new UserIconWrapper {Value = {value}});
                u.IpGeolocation = el.IpAddress.NullIfEmpty();
                u.ExpGrade = (byte)el.LevelId;
                return u;
            }
            catch (Exception e)
            {
                e.Data["raw"] = SharedHelper.UnescapedJsonSerialize(el);
                throw new InvalidDataException("User parse error.", e);
            }
        }).OfType<User.Parsed>().ForEach(u => users[u.Uid] = u);

    public void ResetUsersIcon() => users.Values.ForEach(u => u.Icon = null);

    [GeneratedRegex("^(?<portrait>.+)\\?t=(?<timestamp>[0-9]+)$", RegexOptions.Compiled, matchTimeoutMilliseconds: 100)]
    private static partial Regex ExtractPortraitRegex();
}
