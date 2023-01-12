// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision
{
    public abstract class BaseSubReplyRevision : RevisionWithSplitting<BaseSubReplyRevision>
    {
        public ulong Spid { get; set; }
    }
    public class SubReplyRevision : BaseSubReplyRevision
    {
        [NotMapped] public int AgreeCount
        {
            get => GetSplitEntityValue<SplitAgreeCount, int>(r => r.AgreeCount);
            set => SetSplitEntityValue<SplitAgreeCount, int>(value, (r, v) => r.AgreeCount = v,
                () => new() {TakenAt = TakenAt, Spid = Spid, AgreeCount = value});
        }
        [NotMapped] public int DisagreeCount
        {
            get => GetSplitEntityValue<SplitDisagreeCount, int>(r => r.DisagreeCount);
            set => SetSplitEntityValue<SplitDisagreeCount, int>(value, (r, v) => r.DisagreeCount = v,
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
}
