// ReSharper disable PropertyCanBeMadeInitOnly.Global
// ReSharper disable UnusedAutoPropertyAccessor.Global
namespace tbm.Crawler.Db.Revision;

public abstract class AuthorRevision : ForumScopedRevision
{
    public long Uid { get; set; }
    public required PostType TriggeredBy { get; set; }
}
