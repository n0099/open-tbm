namespace tbm.Crawler
{
    public abstract class BaseRevision
    {
        public uint Time { get; set; }
        public byte[] NullFields { get; set; } = null!;
    }
}
