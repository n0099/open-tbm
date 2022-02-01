namespace tbm.Crawler
{
    public class ReplyPost : IPost
    {
        public ulong Tid { get; init; }
        public ulong Pid { get; init; }
        public uint Floor { get; init; }
        public string? Content { get; init; }
        public long AuthorUid { get; init; }
        public string? AuthorManagerType { get; init; }
        public ushort? AuthorExpGrade { get; init; }
        public uint SubReplyNum { get; init; }
        public uint PostTime { get; init; }
        public bool IsFold { get; init; }
        public int AgreeNum { get; init; }
        public int DisagreeNum { get; init; }
        public string? Location { get; init; }
        public string? SignInfo { get; init; }
        public string? TailInfo { get; init; }
    }
}
