namespace tbm
{
    public class SubReplyPost : IPost
    {
        public ulong Tid { get; init; }
        public ulong Pid { get; init; }
        public ulong Spid { get; init; }
        public string? Content { get; init; }
        public long AuthorUid { get; init; }
        public string? AuthorManagerType { get; init; }
        public ushort AuthorExpGrade { get; init; }
        public uint PostTime { get; init; }
    }
}
