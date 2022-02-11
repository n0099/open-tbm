using System.ComponentModel.DataAnnotations;

namespace tbm.Crawler
{
    public class UserRecord
    {
        [Key] public long Uid { get; set; }
        public string? Name { get; set; }
        public string? DisplayName { get; set; }
        public string AvatarUrl { get; set; } = "";
        public ushort? Gender { get; set; }
        public string? FansNickname { get; set; }
        public string? IconInfo { get; set; }
    }
}
