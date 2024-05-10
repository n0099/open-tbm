// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Revision;

public class ForumModeratorRevision : ForumScopedRevision
{
    public required string Portrait { get; set; }
    public required string ModeratorTypes { get; set; }
}
