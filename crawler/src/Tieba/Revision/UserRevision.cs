namespace tbm.Crawler
{
    public class UserRevision
    {
        public uint Time { get; set; }
        public long Uid { get; set; }
        public string? Name { get; set; }
        public string? DisplayName { get; set; }
        public string? AvatarUrl { get; set; }
        public ushort? Gender { get; set; }
        public string? FansNickname { get; set; }
        public byte[]? IconInfo { get; set; }
    }
}
