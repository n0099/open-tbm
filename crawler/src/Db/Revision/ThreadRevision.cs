// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision
{
    public class ThreadRevision : BaseRevision
    {
        public ulong Tid { get; set; }
        public ulong? ThreadType { get; set; }
        public string? StickyType { get; set; }
        public string? TopicType { get; set; }
        public ushort? IsGood { get; set; }
        public string? AuthorManagerType { get; set; }
        public uint? LatestReplyTime { get; set; }
        public long? LatestReplierUid { get; set; }
        public uint? ReplyCount { get; set; }
        public uint? ViewCount { get; set; }
        public uint? ShareCount { get; set; }
        public int? AgreeCount { get; set; }
        public int? DisagreeCount { get; set; }
        public byte[]? Geolocation { get; set; }
    }
}
