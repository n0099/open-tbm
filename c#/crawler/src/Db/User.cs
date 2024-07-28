namespace tbm.Crawler.Db;

public class User : BaseUser
{
    [Key] public long Uid { get; set; }
    public required string Portrait { get; set; }
    public uint? PortraitUpdatedAt { get; set; }
    public byte? Gender { get; set; }
    public string? FansNickname { get; set; }
    public byte[]? Icon { get; set; }
    public string? IpGeolocation { get; set; }

    public class Parsed : User
    {
        public byte ExpGrade { get; set; }
    }
}
