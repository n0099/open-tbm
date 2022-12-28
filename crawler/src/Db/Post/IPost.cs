// ReSharper disable UnusedMemberInSuper.Global
namespace tbm.Crawler.Db.Post
{
    public interface IPost : IEntityWithTimestampFields, ICloneable
    {
        public ulong Tid { get; set; }
        public long AuthorUid { get; set; }
        [NotMapped] public string? AuthorManagerType { get; set; }
        public uint? LastSeen { get; set; }
    }
}
