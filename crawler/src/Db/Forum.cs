namespace tbm.Crawler.Db
{
    public class Forum
    {
        [Key] public Fid Fid { get; set; }
        public string Name { get; set; } = "";
        public bool IsCrawling { get; set; }
    }
}
