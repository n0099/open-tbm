namespace tbm.Crawler
{
    public class ThreadSaver : BaseSaver<ThreadPost>
    {
        public override FieldsChangeIgnoranceWrapper TiebaUserFieldsChangeIgnorance { get; } = new(
            Update: new()
            { // the value of user gender returned by thread response is always 0
                [typeof(TiebaUser)] = new() {new(nameof(TiebaUser.Gender), true, (ushort)0)}
            },
            Revision: new()
        );

        private readonly Fid _fid;

        public delegate ThreadSaver New(ConcurrentDictionary<Tid, ThreadPost> posts, Fid fid);

        public ThreadSaver(ILogger<ThreadSaver> logger, ConcurrentDictionary<Tid, ThreadPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override SaverChangeSet<ThreadPost> SavePosts(TbmDbContext db) => SavePosts(db,
                PredicateBuilder.New<ThreadPost>(p => Posts.Keys.Any(id => id == p.Tid)),
                PredicateBuilder.New<PostIndex>(i => i.Type == "thread" && Posts.Keys.Any(id => id == i.Tid)),
                p => p.Tid,
                i => i.Tid,
                p => new() {Type = "thread", Fid = _fid, Tid = p.Tid, PostTime = p.PostTime},
                p => new ThreadRevision {Time = p.UpdatedAt, Tid = p.Tid});
    }
}
