namespace tbm.Crawler
{
    public class ReplyRevision : IPostRevision
    {
        public object Clone() => MemberwiseClone();
        public uint Time { get; set; }
        public ulong Tid { get; set; }
        public ulong Pid { get; set; }
    }
}
