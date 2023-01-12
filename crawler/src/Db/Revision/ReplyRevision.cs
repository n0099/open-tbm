// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision
{
    public class ReplyRevision : BaseRevision
    {
        public ulong Pid { get; set; }
        public uint Floor { get; set; }
        public uint SubReplyCount { get; set; }
        public ushort? IsFold { get; set; }
        public int AgreeCount { get; set; }
        public int? DisagreeCount { get; set; }
        public byte[]? Geolocation { get; set; }
    }
}
