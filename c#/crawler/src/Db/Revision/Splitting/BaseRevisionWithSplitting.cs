// ReSharper disable UnusedMemberInSuper.Global
// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision.Splitting;

public abstract class BaseRevisionWithSplitting : RowVersionedEntity
{
    public uint TakenAt { get; set; }
    public ushort? NullFieldsBitMask { get; set; }
    public abstract bool IsAllFieldsIsNullExceptSplit();
}
