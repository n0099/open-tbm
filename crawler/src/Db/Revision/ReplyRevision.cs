// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision
{
    public abstract class BaseReplyRevision : RevisionWithSplitting<BaseReplyRevision>
    {
        public ulong Pid { get; set; }
    }
    public class ReplyRevision : BaseReplyRevision
    {
        [NotMapped] public uint Floor
        {
            get => GetSplitEntityValue<SplitFloor, uint>(r => r.Floor);
            set => SetSplitEntityValue<SplitFloor, uint>(value, (r, v) => r.Floor = v,
                () => new() {TakenAt = TakenAt, Pid = Pid, Floor = value});
        }
        [NotMapped] public uint SubReplyCount
        {
            get => GetSplitEntityValue<SplitSubReplyCount, uint>(r => r.SubReplyCount);
            set => SetSplitEntityValue<SplitSubReplyCount, uint>(value, (r, v) => r.SubReplyCount = v,
                () => new() {TakenAt = TakenAt, Pid = Pid, SubReplyCount = value});
        }
        public ushort? IsFold { get; set; }
        [NotMapped] public int AgreeCount
        {
            get => GetSplitEntityValue<SplitAgreeCount, int>(r => r.AgreeCount);
            set => SetSplitEntityValue<SplitAgreeCount, int>(value, (r, v) => r.AgreeCount = v,
                () => new() {TakenAt = TakenAt, Pid = Pid, AgreeCount = value});
        }
        public int? DisagreeCount { get; set; }
        public byte[]? Geolocation { get; set; }

        public override bool IsAllFieldsIsNullExceptSplit() =>
            NullFieldsBitMask == null && IsFold == null && DisagreeCount == null && Geolocation == null;

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
}
