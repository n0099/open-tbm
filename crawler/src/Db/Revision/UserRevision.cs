// ReSharper disable UnusedAutoPropertyAccessor.Global
// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision;

public abstract class BaseUserRevision : RevisionWithSplitting<BaseUserRevision>
{
    public long Uid { get; set; }
    public string TriggeredBy { get; set; } = "";
}
public class UserRevision : BaseUserRevision
{
    public string? Name { get; set; }
    [NotMapped] public string? DisplayName
    {
        get => GetSplitEntityValue<SplitDisplayName, string?>(r => r.DisplayName);
        set => SetSplitEntityValue<SplitDisplayName, string?>(value, (r, v) => r.DisplayName = v,
            () => new() {TakenAt = TakenAt, Uid = Uid, TriggeredBy = TriggeredBy, DisplayName = value});
    }
    public string? Portrait { get; set; }
    [NotMapped] public uint? PortraitUpdatedAt
    {
        get => GetSplitEntityValue<SplitPortraitUpdatedAt, uint?>(r => r.PortraitUpdatedAt);
        set => SetSplitEntityValue<SplitPortraitUpdatedAt, uint?>(value, (r, v) => r.PortraitUpdatedAt = v,
            () => new() {TakenAt = TakenAt, Uid = Uid, TriggeredBy = TriggeredBy, PortraitUpdatedAt = value});
    }
    public ushort? Gender { get; set; }
    public byte[]? Icon { get; set; }
    [NotMapped] public string? IpGeolocation
    {
        get => GetSplitEntityValue<SplitIpGeolocation, string?>(r => r.IpGeolocation);
        set => SetSplitEntityValue<SplitIpGeolocation, string?>(value, (r, v) => r.IpGeolocation = v,
            () => new() {TakenAt = TakenAt, Uid = Uid, TriggeredBy = TriggeredBy, IpGeolocation = value});
    }

    public override bool IsAllFieldsIsNullExceptSplit() =>
        NullFieldsBitMask == null
        && Name == null
        && Portrait == null
        && Gender == null
        && Icon == null;

    public class SplitDisplayName : BaseUserRevision
    {
        public string? DisplayName { get; set; }
    }
    public class SplitPortraitUpdatedAt : BaseUserRevision
    {
        public uint? PortraitUpdatedAt { get; set; }
    }
    public class SplitIpGeolocation : BaseUserRevision
    {
        public string? IpGeolocation { get; set; }
    }
}
