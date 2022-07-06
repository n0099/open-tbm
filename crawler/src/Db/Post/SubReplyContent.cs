namespace tbm.Crawler
{
    public class SubReplyContent
    {
        [Key] public ulong Spid { get; set; }
        public byte[]? Content { get; set; }
    }
}
