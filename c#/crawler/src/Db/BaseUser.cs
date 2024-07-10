namespace tbm.Crawler.Db;

public abstract class BaseUser : TimestampedEntity
{
    public string? Name { get; set; }
    public string? DisplayName { get; set; }
}
