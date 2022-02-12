using System.ComponentModel.DataAnnotations.Schema;

namespace tbm.Crawler
{
    public class ThreadRevision : IPostRevision
    {
        public uint Time { get; set; }
        public ulong Tid { get; set; }
        public string? StickyType { get; set; }
        public string? TopicType { get; set; }
        public bool? IsGood { get; set; }
        public string? AuthorManagerType { get; set; }
        public uint? LatestReplyTime { get; set; }
        public long? LatestReplierUid { get; set; }
        public uint? ReplyNum { get; set; }
        public uint? ViewNum { get; set; }
        public uint? ShareNum { get; set; }
        public uint? AgreeNum { get; set; }
        public uint? DisagreeNum { get; set; }
        // changes for props with [NotMapped] attribute will be ignored when inserting
        [NotMapped] public string? ZanInfo { get; set; }
    }
}
