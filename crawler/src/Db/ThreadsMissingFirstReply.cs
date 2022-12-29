namespace tbm.Crawler.Db
{
    public class ThreadsMissingFirstReply
    {
        [Key] public ulong Tid { get; set; }
        public ulong? Pid { get; set; }
        public byte[]? Excerpt { get; set; }
    }
}
