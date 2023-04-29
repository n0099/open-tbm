namespace tbm.Crawler.Db;

public class TiebaUser : ITimestampingEntity
{
    [Key] public long Uid { get; set; }
    public string? Name { get; set; }
    public string? DisplayName { get; set; }
    public string Portrait { get; set; } = "";
    public uint? PortraitUpdatedAt { get; set; }
    public ushort? Gender { get; set; }
    public string? FansNickname { get; set; }
    public byte[]? Icon { get; set; }
    public string? IpGeolocation { get; set; }
    public uint CreatedAt { get; set; }
    public uint? UpdatedAt { get; set; }

    public static TiebaUser CreateLatestReplier(long uid, string? name, string? displayName) =>
        new() {Uid = uid, Name = name, DisplayName = displayName};
}
