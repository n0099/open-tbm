namespace tbm.Crawler
{
    public class PostIndex
    {
        public string Type { get; set; } = "";
        public uint Fid { get; set; }
        public ulong Tid { get; set; }
        public ulong? Pid { get; set; }
        public ulong? Spid { get; set; }
        public uint? PostTime { get; set; }
    }
}
