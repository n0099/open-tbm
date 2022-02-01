namespace tbm
{
    public interface IPost
    {
        public ulong Tid { get; init; }
        public long AuthorUid { get; init; }
    }
}
