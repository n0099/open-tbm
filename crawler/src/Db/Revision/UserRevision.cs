namespace tbm.Crawler
{
    public class UserRevision : BaseRevision
    {
        public long Uid { get; set; }
        public string TriggeredBy { get; set; } = "";
        public string? Name { get; set; }
        public string? DisplayName { get; set; }
        public string? Portrait { get; set; }
        public uint? PortraitUpdateTime { get; set; }
        public ushort? Gender { get; set; }
        public string? FansNickname { get; set; }
        public byte[]? IconInfo { get; set; }
        public string? IpProvince { get; set; }
    }
}
