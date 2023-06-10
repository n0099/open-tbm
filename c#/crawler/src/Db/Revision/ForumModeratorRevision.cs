namespace tbm.Crawler.Db.Revision;

public class ForumModeratorRevision
{
    public uint DiscoveredAt { get; set; }
    public uint Fid { get; set; }
    public string Portrait { get; set; } = "";
    public string ModeratorType { get; set; } = "";
}
