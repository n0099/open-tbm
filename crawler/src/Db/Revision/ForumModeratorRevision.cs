// ReSharper disable UnusedAutoPropertyAccessor.Global
// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision
{
    public class ForumModeratorRevision
    {
        public uint Time { get; set; }
        public uint Fid { get; set; }
        public long Uid { get; set; }
        public string Portrait { get; set; } = "";
        public string? Type { get; set; }
    }
}
