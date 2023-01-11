// ReSharper disable UnusedAutoPropertyAccessor.Global
// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision
{
    public abstract class BaseRevision
    {
        public uint TakenAt { get; set; }
        public ushort? NullFieldsBitMask { get; set; }
    }
}
