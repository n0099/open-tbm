// ReSharper disable UnusedMember.Global
namespace tbm.Crawler.Db.Revision
{
    public interface IRevision
    {
        public uint TakenAt { get; set; }
        public ushort? NullFieldsBitMask { get; set; }
    }
}
