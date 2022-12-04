namespace tbm.Crawler
{
    public class ThreadPost : IPost
    {
        public object Clone() => MemberwiseClone();
        [Key] public ulong Tid { get; set; }
        public ulong FirstPid { get; set; }
        public ulong ThreadType { get; set; }
        public string? StickyType { get; set; }
        public string? TopicType { get; set; }
        public ushort? IsGood { get; set; }
        public string Title { get; set; } = "";
        public long AuthorUid { get; set; }
        public string? AuthorManagerType { get; set; }
        public uint PostTime { get; set; }
        public uint LatestReplyTime { get; set; }
        public long? LatestReplierUid { get; set; }
        public uint? ReplyCount { get; set; }
        public uint? ViewCount { get; set; }
        public uint? ShareCount { get; set; }
        public int? AgreeCount { get; set; }
        public int? DisagreeCount { get; set; }
        public byte[]? Zan { get; set; }
        public byte[]? Geolocation { get; set; }
        public string? AuthorPhoneType { get; set; }
        public uint CreatedAt { get; set; }
        public uint? UpdatedAt { get; set; }
        public uint? LastSeen { get; set; }
    }
}
