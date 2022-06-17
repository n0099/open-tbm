namespace tbm.Crawler
{
    public class ReplySaver : BaseSaver<ReplyPost>
    {
        public override FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance { get; } = new(
            Update: (_, propertyName, originalValue, currentValue) =>
                // the value of IconInfo in response of reply crawler might be null even if the user haven't change his icon info
                propertyName == nameof(TiebaUser.IconInfo) && currentValue is null,
            (_, propertyName, originalValue, currentValue) =>
                // the value of user gender in thread response might be 0 but in reply response it won't be 0
                propertyName == nameof(TiebaUser.Gender) && (ushort?)originalValue is 0 && (ushort?)currentValue is not 0);

        private readonly Fid _fid;

        public delegate ReplySaver New(ConcurrentDictionary<PostId, ReplyPost> posts, Fid fid);

        public ReplySaver(ILogger<ReplySaver> logger, ConcurrentDictionary<PostId, ReplyPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override SaverChangeSet<ReplyPost> SavePosts(TbmDbContext db) => SavePosts(db,
            PredicateBuilder.New<ReplyPost>(p => Posts.Keys.Any(id => id == p.Pid)),
            PredicateBuilder.New<PostIndex>(i => i.Type == "reply" && Posts.Keys.Any(id => id == i.Pid)),
            p => p.Pid,
            i => i.Pid,
            p => new() {Type = "reply", Fid = _fid, Tid = p.Tid, Pid = p.Pid, PostTime = p.PostTime},
            p => new ReplyRevision {Time = p.UpdatedAt, Pid = p.Pid},
            () => new ReplyRevisionNullFields());
    }
}
