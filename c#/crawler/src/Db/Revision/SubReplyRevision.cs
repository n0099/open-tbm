// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision;

public abstract class BaseSubReplyRevision : RevisionWithSplitting<BaseSubReplyRevision>
{
    public ulong Spid { get; set; }
}

public class SubReplyRevision : BaseSubReplyRevision
{
    [NotMapped]
    public int AgreeCount
    {
        get => GetSplitEntityValue<SplitAgreeCount, int>(s => s.AgreeCount);
        set => SetSplitEntityValue<SplitAgreeCount, int>(value, (s, v) => s.AgreeCount = v,
            () => new() {TakenAt = TakenAt, Spid = Spid, AgreeCount = value});
    }

    [NotMapped]
    public int DisagreeCount
    {
        get => GetSplitEntityValue<SplitDisagreeCount, int>(s => s.DisagreeCount);
        set => SetSplitEntityValue<SplitDisagreeCount, int>(value, (s, v) => s.DisagreeCount = v,
            () => new() {TakenAt = TakenAt, Spid = Spid, DisagreeCount = value});
    }

    public override bool IsAllFieldsIsNullExceptSplit() => true;

    public class SplitAgreeCount : BaseSubReplyRevision
    {
        public int AgreeCount { get; set; }
    }

    public class SplitDisagreeCount : BaseSubReplyRevision
    {
        public int DisagreeCount { get; set; }
    }
}
