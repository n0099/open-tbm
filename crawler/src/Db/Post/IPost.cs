// ReSharper disable UnusedMemberInSuper.Global
namespace tbm.Crawler.Db.Post
{
    public interface IPost : ITimestampingEntity, ICloneable
    {
        public ulong Tid { get; set; }
        public long AuthorUid { get; set; }
        public uint? LastSeenAt { get; set; }
    }
}
