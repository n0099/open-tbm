// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision
{
    public abstract class AuthorRevision
    {
        public uint Time { get; set; }
        public uint Fid { get; set; }
        public long Uid { get; set; }
    }
}
