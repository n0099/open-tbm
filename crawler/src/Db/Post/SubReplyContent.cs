namespace tbm.Crawler.Db.Post
{
    public class SubReplyContent
    {
        [Key] public ulong Spid { get; set; }
        public byte[]? Content { get; set; }
    }
}
