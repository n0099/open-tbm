// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Crawler.Db.Post;

public class SubReplyPost : PostWithContentAndAuthorExpGrade
{
    [Column(TypeName = "bigint")]
    public ulong Pid { get; set; }
    [Key] [Column(TypeName = "bigint")]
    public ulong Spid { get; set; }
    public uint PostedAt { get; set; }
    public int? AgreeCount { get; set; }
    public int? DisagreeCount { get; set; }

    public override object Clone() => MemberwiseClone();
}
