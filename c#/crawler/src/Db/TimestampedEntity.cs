namespace tbm.Crawler.Db;

public abstract class TimestampedEntity : RowVersionedEntity
{
    public uint CreatedAt { get; set; }
    public uint? UpdatedAt { get; set; }
}
