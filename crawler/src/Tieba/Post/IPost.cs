namespace tbm.Crawler
{
    public interface IPost
    {
        public ulong Tid { get; set; }
        public long AuthorUid { get; set; }
        public string? AuthorManagerType { get; set; }
        public uint CreatedAt { get; set; }
        public uint UpdatedAt { get; set; }
        public string[] JsonTypeProps { get; }
    }
}
