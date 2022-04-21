namespace tbm.Crawler
{
    public class ThreadSaver : BaseSaver<ThreadPost>
    {
        private readonly Fid _fid;

        public delegate ThreadSaver New(ConcurrentDictionary<Tid, ThreadPost> posts, Fid fid);

        public ThreadSaver(ILogger<ThreadSaver> logger, ConcurrentDictionary<Tid, ThreadPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override ILookup<bool, ThreadPost> SavePosts(TbmDbContext db)
        {
            var ret = SavePosts(db,
                PredicateBuilder.New<ThreadPost>(p => Posts.Keys.Any(id => id == p.Tid)),
                PredicateBuilder.New<PostIndex>(i => i.Type == "thread" && Posts.Keys.Any(id => id == i.Tid)),
                p => p.Tid,
                i => i.Tid,
                p => new PostIndex {Type = "thread", Fid = _fid, Tid = p.Tid, PostTime = p.PostTime},
                p => new ThreadRevision {Time = p.UpdatedAt, Tid = p.Tid});

            // prevent overwrite with default null value on field which will be update by ThreadLateSaveInfoCrawler
            db.ChangeTracker.Entries<ThreadPost>().ForEach(e => e.Properties
                .Where(p => p.Metadata.Name is nameof(ThreadPost.AuthorPhoneType))
                .ForEach(p => p.IsModified = false));
            return ret;
        }
    }
}
