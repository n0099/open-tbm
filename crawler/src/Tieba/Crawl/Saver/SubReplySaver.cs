namespace tbm.Crawler
{
    public class SubReplySaver : BaseSaver<SubReplyPost>
    {
        private readonly Fid _fid;

        public delegate SubReplySaver New(ConcurrentDictionary<ulong, SubReplyPost> posts, Fid fid);

        public SubReplySaver(ILogger<SubReplySaver> logger, ConcurrentDictionary<ulong, SubReplyPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override ILookup<bool, SubReplyPost> SavePosts(TbmDbContext db) => SavePosts(db,
            PredicateBuilder.New<SubReplyPost>(p => Posts.Keys.Any(id => id == p.Spid)),
            PredicateBuilder.New<PostIndex>(i => i.Type == "reply" && Posts.Keys.Any(id => id == i.Spid)),
            p => p.Spid,
            i => i.Spid,
            p => new PostIndex {Type = "reply", Fid = _fid, Tid = p.Tid, Pid = p.Pid, Spid = p.Spid, PostTime = p.PostTime},
            p => new SubReplyRevision {Time = p.UpdatedAt, Spid = p.Spid});
    }
}
