namespace tbm.Crawler
{
    public class ReplyRevision : BaseRevision
    {
        public ulong Pid { get; set; }
        public uint? Floor { get; set; }
        public string? AuthorManagerType { get; set; }
        public ushort? AuthorExpGrade { get; set; }
        public int? SubReplyNum { get; set; }
        public ushort? IsFold { get; set; }
        public int? AgreeNum { get; set; }
        public int? DisagreeNum { get; set; }
        public byte[]? Location { get; set; }
    }
}
