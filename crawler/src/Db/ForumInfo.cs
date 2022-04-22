namespace tbm.Crawler
{
    public class ForumInfo
    {
        [Key] public Fid Fid { get; set; }
        public string Name { get; set; }
        public ushort IsCrawling { get; set; }
    }
}
