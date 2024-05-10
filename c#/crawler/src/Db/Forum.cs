// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db;

public class Forum : RowVersionedEntity
{
    [Key] public Fid Fid { get; set; }
    public required string Name { get; set; }
    public bool IsCrawling { get; set; }
}
