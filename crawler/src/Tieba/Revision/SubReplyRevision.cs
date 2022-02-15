namespace tbm.Crawler
{
    public class SubReplyRevision : PostRevision
    {
        public ulong Spid { get; set; }
        public string? AuthorManagerType { get; set; }
        public ushort? AuthorExpGrade { get; set; }
    }
}
