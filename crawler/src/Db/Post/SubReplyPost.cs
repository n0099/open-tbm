namespace tbm.Crawler
{
    public class SubReplyPost : IPost
    {
        public object Clone() => MemberwiseClone();
        public ulong Tid { get; set; }
        public ulong Pid { get; set; }
        [Key] public ulong Spid { get; set; }
        [NotMapped] public byte[]? Content { get; set; }
        public long AuthorUid { get; set; }
        public string? AuthorManagerType { get; set; }
        public ushort AuthorExpGrade { get; set; }
        public uint PostTime { get; set; }
        public uint CreatedAt { get; set; }
        public uint UpdatedAt { get; set; }
    }
}
