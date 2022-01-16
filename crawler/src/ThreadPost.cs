namespace tbm
{
    public readonly struct ThreadPost
    {
        public ulong Tid { get; init; }
        public ulong FirstPid { get; init; }
        public ulong ThreadType { get; init; }
        public string? StickyType { get; init; }
        public bool IsGood { get; init; }
        public string? TopicType { get; init; }
        public string Title { get; init; }
        public long AuthorUid { get; init; }
        public string? AuthorManagerType { get; init; }
        public uint? PostTime { get; init; }
        public uint LatestReplyTime { get; init; }
        public long LatestReplierUid { get; init; }
        public uint ReplyNum { get; init; }
        public uint ViewNum { get; init; }
        public uint? ShareNum { get; init; }
        public uint AgreeNum { get; init; }
        public uint DisagreeNum { get; init; }
        public string? Location { get; init; }
        public string? ZanInfo { get; init; }
    }
}
