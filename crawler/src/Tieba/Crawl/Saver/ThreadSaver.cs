namespace tbm.Crawler
{
    public class ThreadSaver : BaseSaver<ThreadPost>
    {
        public override FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance { get; } = new(
            Update: (_, propertyName, originalValue, currentValue) => propertyName switch
            { // the value of user gender in thread response might be 0 but in reply response it won't be 0
                nameof(TiebaUser.Gender) when (ushort?)currentValue is 0 && (ushort?)originalValue is not 0 => true,
                // IconInfo.SpriteInfo will be an empty array and the icon url is a smaller one, so we should mark it as null temporarily
                // note this will cause we can't record when did a user update its iconinfo to null since these null values have been ignored in reply and sub reply saver
                nameof(TiebaUser.IconInfo) => true,
                _ => false
            }, (_, _, _, _) => false);

        protected override Dictionary<string, ushort> RevisionNullFieldsBitMasks { get; } = new()
        {
            {nameof(ThreadPost.StickyType),        1},
            {nameof(ThreadPost.TopicType),         1 << 1},
            {nameof(ThreadPost.IsGood),            1 << 2},
            {nameof(ThreadPost.AuthorManagerType), 1 << 3},
            {nameof(ThreadPost.LatestReplierUid),  1 << 4},
            {nameof(ThreadPost.ReplyNum),          1 << 5},
            {nameof(ThreadPost.ViewNum),           1 << 6},
            {nameof(ThreadPost.ShareNum),          1 << 7},
            {nameof(ThreadPost.AgreeNum),          1 << 8},
            {nameof(ThreadPost.DisagreeNum),       1 << 9},
            {nameof(ThreadPost.Geolocation),       1 << 10}
        };

        private readonly Fid _fid;

        public delegate ThreadSaver New(ConcurrentDictionary<Tid, ThreadPost> posts, Fid fid);

        public ThreadSaver(ILogger<ThreadSaver> logger, ConcurrentDictionary<Tid, ThreadPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override SaverChangeSet<ThreadPost> SavePosts(TbmDbContext db) => SavePosts(db,
                PredicateBuilder.New<ThreadPost>(t => Posts.Keys.Contains(t.Tid)),
                PredicateBuilder.New<PostIndex>(pi => pi.Type == "thread" && Posts.Keys.Contains(pi.Tid)),
                t => t.Tid,
                pi => pi.Tid,
                t => new() {Type = "thread", Fid = _fid, Tid = t.Tid, PostTime = t.PostTime},
                t => new ThreadRevision {Time = t.UpdatedAt ?? t.CreatedAt, Tid = t.Tid});
    }
}
