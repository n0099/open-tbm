// ReSharper disable UnusedMemberInSuper.Global
namespace tbm.Crawler.Db.Revision.Splitting;

public interface IRevision
{
    public uint TakenAt { get; set; }
    public ushort? NullFieldsBitMask { get; set; }
    public bool IsAllFieldsIsNullExceptSplit();
}
