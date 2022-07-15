namespace tbm.Crawler
{
    public class SubReplyRevision : BaseRevision
    {
        public ulong Spid { get; set; }
        public string? AuthorManagerType { get; set; }
        public ushort? AuthorExpGrade { get; set; }
        public int? AgreeNum { get; set; }
        public int? DisagreeNum { get; set; }
    }
}
