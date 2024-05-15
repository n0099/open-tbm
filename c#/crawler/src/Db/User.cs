namespace tbm.Crawler.Db;

public class User : TimestampedEntity
{
    [Key] public long Uid { get; set; }
    public string? Name { get; set; }
    public string? DisplayName { get; set; }
    public required string Portrait { get; set; }
    public uint? PortraitUpdatedAt { get; set; }
    public byte? Gender { get; set; }
    public string? FansNickname { get; set; }
    public byte[]? Icon { get; set; }
    public string? IpGeolocation { get; set; }

    public static User CreateLatestReplier(long uid, string? name, string? displayName) =>
        new() {Uid = uid, Name = name, DisplayName = displayName, Portrait = ""};

    public class EqualityComparer : EqualityComparer<User>
    {
        public static EqualityComparer Instance { get; } = new();

        public override bool Equals(User? x, User? y) => x == y || (
            x != null && y != null &&
            (x.Uid, x.Name, x.DisplayName, x.Portrait, x.PortraitUpdatedAt, x.Gender, x.FansNickname, x.IpGeolocation)
            == (y.Uid, y.Name, y.DisplayName, y.Portrait, y.PortraitUpdatedAt, y.Gender, y.FansNickname, y.IpGeolocation)
            && (x.Icon == y.Icon
                || (x.Icon != null && y.Icon != null && ByteArrayEqualityComparer.Instance.Equals(x.Icon, y.Icon))));

        public override int GetHashCode(User obj)
        {
            var hash = new HashCode();
            hash.Add(obj.Uid);
            hash.Add(obj.Name);
            hash.Add(obj.DisplayName);
            hash.Add(obj.Portrait);
            hash.Add(obj.PortraitUpdatedAt);
            hash.Add(obj.Gender);
            hash.Add(obj.FansNickname);
            hash.AddBytes(obj.Icon);
            hash.Add(obj.IpGeolocation);
            return hash.ToHashCode();
        }
    }
}
