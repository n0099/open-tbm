// ReSharper disable UnusedAutoPropertyAccessor.Global
// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision
{
    public class ForumModeratorRevision
    {
        [Key] public uint Id { get; set; }
        public uint DiscoveredAt { get; set; }
        public uint Fid { get; set; }
        public string Portrait { get; set; } = "";
        public string? ModeratorType { get; set; }
    }
}
