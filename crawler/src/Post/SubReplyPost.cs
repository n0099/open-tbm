namespace tbm.Crawler
{
    public class SubReplyPost : IPost
    {
        public ulong Tid { get; set; }
        public ulong Pid { get; set; }
        public ulong Spid { get; set; }
        public string? Content { get; set; }
        public long AuthorUid { get; set; }
        public string? AuthorManagerType { get; set; }
        public ushort AuthorExpGrade { get; set; }
        public uint PostTime { get; set; }
    }
}
