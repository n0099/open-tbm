namespace tbm.Crawler.Db;

public interface ITimestampedEntity
{
    public uint CreatedAt { get; set; }
    public uint? UpdatedAt { get; set; }
}
