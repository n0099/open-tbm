namespace tbm.Crawler.Db.Revision
{
    public abstract class BaseRevision
    {
        public uint Time { get; set; }
        public ushort? NullFieldsBitMask { get; set; }
    }
}
