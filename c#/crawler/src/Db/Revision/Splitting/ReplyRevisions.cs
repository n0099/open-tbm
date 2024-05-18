// ReSharper disable PropertyCanBeMadeInitOnly.Global
// ReSharper disable UnusedAutoPropertyAccessor.Global
// ReSharper disable UnusedMember.Global
namespace tbm.Crawler.Db.Revision.Splitting;

public abstract class BaseReplyRevision : RevisionWithSplitting<BaseReplyRevision>
{
    [Column(TypeName = "bigint")]
    public ulong Pid { get; set; }
}

public class ReplyRevision : BaseReplyRevision
{
    [NotMapped]
    public uint Floor
    {
        get => GetSplitEntityValue<SplitFloor, uint>(s => s.Floor);
        set => SetSplitEntityValue<SplitFloor, uint>(value, (s, v) => s.Floor = v,
            () => new() {TakenAt = TakenAt, Pid = Pid, Floor = value});
    }

    [NotMapped]
    public uint SubReplyCount
    {
        get => GetSplitEntityValue<SplitSubReplyCount, uint>(s => s.SubReplyCount);
        set => SetSplitEntityValue<SplitSubReplyCount, uint>(value, (s, v) => s.SubReplyCount = v,
            () => new() {TakenAt = TakenAt, Pid = Pid, SubReplyCount = value});
    }

    public byte? IsFold { get; set; }

    [NotMapped]
    public int AgreeCount
    {
        get => GetSplitEntityValue<SplitAgreeCount, int>(s => s.AgreeCount);
        set => SetSplitEntityValue<SplitAgreeCount, int>(value, (s, v) => s.AgreeCount = v,
            () => new() {TakenAt = TakenAt, Pid = Pid, AgreeCount = value});
    }

    public int? DisagreeCount { get; set; }
    public byte[]? Geolocation { get; set; }

    public override bool IsAllFieldsIsNullExceptSplit() =>
        (NullFieldsBitMask, IsFold, DisagreeCount, Geolocation) == default;

    public class SplitFloor : BaseReplyRevision
    {
        public uint Floor { get; set; }
    }

    public class SplitSubReplyCount : BaseReplyRevision
    {
        public uint SubReplyCount { get; set; }
    }

    public class SplitAgreeCount : BaseReplyRevision
    {
        public int AgreeCount { get; set; }
    }
}
