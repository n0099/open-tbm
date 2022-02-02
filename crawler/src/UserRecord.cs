namespace tbm.Crawler
{
    public class UserRecord
    {
        public long Uid { get; init; }
        public string? Name { get; init; }
        public string? DisplayName { get; init; }
        public string AvatarUrl { get; init; } = "";
        public ushort? Gender { get; init; }
        public string? FansNickname { get; init; }
        public string? IconInfo { get; init; }
    }
}
