// ReSharper disable PropertyCanBeMadeInitOnly.Global
// ReSharper disable UnusedAutoPropertyAccessor.Global
// ReSharper disable UnusedMember.Global
namespace tbm.Crawler.Db.Revision.Splitting;

public abstract class BaseThreadRevision : RevisionWithSplitting<BaseThreadRevision>
{
    [Column(TypeName = "bigint")]
    public ulong Tid { get; set; }
}

public class ThreadRevision : BaseThreadRevision
{
    public ulong? ThreadType { get; set; }
    public string? StickyType { get; set; }
    public string? TopicType { get; set; }
    public byte? IsGood { get; set; }
    public uint? LatestReplyPostedAt { get; set; }
    public uint? LatestReplierId { get; set; }
    public uint? ReplyCount { get; set; }

    [NotMapped]
    public uint ViewCount
    {
        get => GetSplitEntityValue<SplitViewCount, uint>(s => s.ViewCount);
        set => SetSplitEntityValue<SplitViewCount, uint>(value, (s, v) => s.ViewCount = v,
            () => new() {TakenAt = TakenAt, Tid = Tid, ViewCount = value});
    }

    public uint? ShareCount { get; set; }
    public int? AgreeCount { get; set; }
    public int? DisagreeCount { get; set; }
    public byte[]? Geolocation { get; set; }

    public override bool IsAllFieldsIsNullExceptSplit() =>
        (NullFieldsBitMask,
            ThreadType,
            StickyType,
            TopicType,
            IsGood,
            LatestReplyPostedAt,
            LatestReplierId,
            ReplyCount,
            ShareCount,
            AgreeCount,
            DisagreeCount,
            Geolocation)
        == default;

    public class SplitViewCount : BaseThreadRevision
    {
        public uint ViewCount { get; set; }
    }
}
