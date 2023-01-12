// ReSharper disable UnusedMember.Global
// ReSharper disable UnusedMemberInSuper.Global
namespace tbm.Crawler.Db.Revision
{
    public interface IRevision
    {
        public uint TakenAt { get; set; }
        public ushort? NullFieldsBitMask { get; set; }
        public bool IsAllFieldsIsNullExceptSplit();
    }
}
