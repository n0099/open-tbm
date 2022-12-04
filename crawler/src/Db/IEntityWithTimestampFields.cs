namespace tbm.Crawler.Db
{
    public interface IEntityWithTimestampFields
    {
        public uint CreatedAt { get; set; }
        public uint? UpdatedAt { get; set; }
    }
}
