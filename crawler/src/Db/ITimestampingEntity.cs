namespace tbm.Crawler.Db;

public interface ITimestampingEntity
{
    public uint CreatedAt { get; set; }
    public uint? UpdatedAt { get; set; }
}
