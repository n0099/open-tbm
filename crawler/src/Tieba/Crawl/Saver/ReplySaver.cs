namespace tbm.Crawler
{
    public class ReplySaver : BaseSaver<ReplyPost>
    {
        private readonly Fid _fid;

        public delegate ReplySaver New(ConcurrentDictionary<ulong, ReplyPost> posts, uint fid);

        public ReplySaver(ILogger<ReplySaver> logger, ConcurrentDictionary<ulong, ReplyPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override ILookup<bool, ReplyPost> SavePosts(TbmDbContext db)
        {
            return SavePosts(db,
                PredicateBuilder.New<ReplyPost>(p => Posts.Keys.Any(id => id == p.Pid)),
                PredicateBuilder.New<PostIndex>(i => i.Type == "reply" && Posts.Keys.Any(id => id == i.Pid)),
                p => p.Pid,
                i => i.Pid,
                p => new PostIndex {Type = "reply", Fid = _fid, Tid = p.Tid, Pid = p.Pid, PostTime = p.PostTime},
                (p) => new ReplyRevision {Time = p.UpdatedAt, Pid = p.Pid});
            /*
            if (_parentThread == null) return;
            var parentThread = new ThreadPost
            {
                Tid = _tid,
                AuthorPhoneType = _parentThread.AuthorPhoneType,
                AntiSpamInfo = _parentThread.AntiSpamInfo
            };
            db.Attach(parentThread);
            db.Entry(parentThread).Properties
                .Where(p => p.Metadata.Name is nameof(ThreadLateSaveInfo.AuthorPhoneType) or nameof(ThreadLateSaveInfo.AntiSpamInfo))
                .ForEach(p => p.IsModified = true);
            */
        }
    }
}
