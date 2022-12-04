namespace tbm.Crawler
{
    public class ThreadSaver : BaseSaver<ThreadPost>
    {
        public override FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance { get; } = new(
            Update: (_, propName, oldValue, newValue) => propName switch
            { // the value of user gender in thread response might be 0 but in reply response it won't be 0
                nameof(TiebaUser.Gender) when (ushort?)newValue is 0 && (ushort?)oldValue is not 0 => true,
                // Icon.SpriteInfo will be an empty array and the icon url is a smaller one, so we should mark it as null temporarily
                // note this will cause we can't record when did a user update its iconinfo to null since these null values have been ignored in reply and sub reply saver
                nameof(TiebaUser.Icon) => true,
                _ => false
            }, (_, _, _, _) => false);

        protected override Dictionary<string, ushort> RevisionNullFieldsBitMasks { get; } = new()
        {
            {nameof(ThreadPost.StickyType),        1},
            {nameof(ThreadPost.TopicType),         1 << 1},
            {nameof(ThreadPost.IsGood),            1 << 2},
            {nameof(ThreadPost.AuthorManagerType), 1 << 3},
            {nameof(ThreadPost.LatestReplierUid),  1 << 4},
            {nameof(ThreadPost.ReplyCount),        1 << 5},
            {nameof(ThreadPost.ViewCount),         1 << 6},
            {nameof(ThreadPost.ShareCount),        1 << 7},
            {nameof(ThreadPost.AgreeCount),        1 << 8},
            {nameof(ThreadPost.DisagreeCount),     1 << 9},
            {nameof(ThreadPost.Geolocation),       1 << 10}
        };

        public delegate ThreadSaver New(ConcurrentDictionary<Tid, ThreadPost> posts);

        public ThreadSaver(ILogger<ThreadSaver> logger, ConcurrentDictionary<Tid, ThreadPost> posts) : base(logger, posts) { }

        public override SaverChangeSet<ThreadPost> SavePosts(TbmDbContext db) => SavePosts(db, t => t.Tid,
            PredicateBuilder.New<ThreadPost>(t => Posts.Keys.Contains(t.Tid)),
            t => new ThreadRevision {Time = t.UpdatedAt ?? t.CreatedAt, Tid = t.Tid});
    }
}
