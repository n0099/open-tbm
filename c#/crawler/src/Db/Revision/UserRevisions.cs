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

    [NotMapped]
    public string? DisplayName
    {
        get => GetSplitEntityValue<SplitDisplayName, string?>(s => s.DisplayName);
        set => SetSplitEntityValue<SplitDisplayName, string?>(value, (s, v) => s.DisplayName = v,
            () => new() {TakenAt = TakenAt, Uid = Uid, TriggeredBy = TriggeredBy, DisplayName = value});
    }

    public string? Portrait { get; set; }

    [NotMapped]
    public uint? PortraitUpdatedAt
    {
        get => GetSplitEntityValue<SplitPortraitUpdatedAt, uint?>(s => s.PortraitUpdatedAt);
        set => SetSplitEntityValue<SplitPortraitUpdatedAt, uint?>(value, (s, v) => s.PortraitUpdatedAt = v,
            () => new() {TakenAt = TakenAt, Uid = Uid, TriggeredBy = TriggeredBy, PortraitUpdatedAt = value});
    }

    public ushort? Gender { get; set; }
    public byte[]? Icon { get; set; }

    [NotMapped]
    public string? IpGeolocation
    {
        get => GetSplitEntityValue<SplitIpGeolocation, string?>(s => s.IpGeolocation);
        set => SetSplitEntityValue<SplitIpGeolocation, string?>(value, (s, v) => s.IpGeolocation = v,
            () => new() {TakenAt = TakenAt, Uid = Uid, TriggeredBy = TriggeredBy, IpGeolocation = value});
    }

    public override bool IsAllFieldsIsNullExceptSplit() =>
        (NullFieldsBitMask, Name, Portrait, Gender, Icon) == default;

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
