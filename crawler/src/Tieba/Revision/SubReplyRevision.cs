namespace tbm.Crawler
{
    public class SubReplyRevision : IPostRevision
    {
        public object Clone() => MemberwiseClone();
        public uint Time { get; set; }
        public ulong Tid { get; set; }
        public ulong Pid { get; set; }
        public ulong Spid { get; set; }
    }
}
