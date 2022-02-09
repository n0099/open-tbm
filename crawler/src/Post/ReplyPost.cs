namespace tbm.Crawler
{
    public class ReplyPost : IPost
    {
        public ulong Tid { get; set; }
        public ulong Pid { get; set; }
        public uint Floor { get; set; }
        public string? Content { get; set; }
        public long AuthorUid { get; set; }
        public string? AuthorManagerType { get; set; }
        public ushort? AuthorExpGrade { get; set; }
        public uint SubReplyNum { get; set; }
        public uint PostTime { get; set; }
        public bool IsFold { get; set; }
        public int AgreeNum { get; set; }
        public int DisagreeNum { get; set; }
        public string? Location { get; set; }
        public string? SignInfo { get; set; }
        public string? TailInfo { get; set; }
    }
}
