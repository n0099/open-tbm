using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace tbm.Crawler
{
    public class ThreadPost : ThreadLateSaveInfo, IPost
    {
        [Key] public ulong Tid { get; set; }
        public ulong FirstPid { get; set; }
        public ulong ThreadType { get; set; }
        public string? StickyType { get; set; }
        public string? TopicType { get; set; }
        public bool IsGood { get; set; }
        public string Title { get; set; } = "";
        public long AuthorUid { get; set; }
        public string? AuthorManagerType { get; set; }
        public uint? PostTime { get; set; }
        public uint LatestReplyTime { get; set; }
        public long LatestReplierUid { get; set; }
        public uint ReplyNum { get; set; }
        public uint ViewNum { get; set; }
        public uint? ShareNum { get; set; }
        public uint AgreeNum { get; set; }
        public uint DisagreeNum { get; set; }
        public string? Location { get; set; }
        public string? ZanInfo { get; set; }
        public uint CreatedAt { get; set; }
        public uint UpdatedAt { get; set; }
        [NotMapped] public static readonly string[] JsonTypeFields = {nameof(Location), nameof(ZanInfo)};
    }
}
