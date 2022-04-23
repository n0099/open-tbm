namespace tbm.Crawler
{
    public class ReplySaver : BaseSaver<ReplyPost>
    {
        private readonly Fid _fid;

        public delegate ReplySaver New(ConcurrentDictionary<PostId, ReplyPost> posts, Fid fid);

        public ReplySaver(ILogger<ReplySaver> logger, ConcurrentDictionary<PostId, ReplyPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override ReturnOfSaver<ReplyPost> SavePosts(TbmDbContext db) => SavePosts(db,
            PredicateBuilder.New<ReplyPost>(p => Posts.Keys.Any(id => id == p.Pid)),
            PredicateBuilder.New<PostIndex>(i => i.Type == "reply" && Posts.Keys.Any(id => id == i.Pid)),
            p => p.Pid,
            i => i.Pid,
            p => new PostIndex {Type = "reply", Fid = _fid, Tid = p.Tid, Pid = p.Pid, PostTime = p.PostTime},
            p => new ReplyRevision {Time = p.UpdatedAt, Pid = p.Pid});
    }
}
