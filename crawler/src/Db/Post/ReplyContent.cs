namespace tbm.Crawler
{
    public class ReplyContent
    {
        [Key] public ulong Pid { get; set; }
        public byte[]? Content { get; set; }
    }
}
