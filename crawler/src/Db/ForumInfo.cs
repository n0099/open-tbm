namespace tbm.Crawler
{
    public class ForumInfo
    {
        [Key] public Fid Fid { get; set; }
        public string Name { get; set; } = "";
        public bool IsCrawling { get; set; }
    }
}
