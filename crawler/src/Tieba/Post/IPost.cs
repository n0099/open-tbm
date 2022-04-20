namespace tbm.Crawler
{
    public interface IPost : IEntityWithTimestampFields, ICloneable
    {
        public ulong Tid { get; set; }
        public long AuthorUid { get; set; }
        public string? AuthorManagerType { get; set; }
    }
}
