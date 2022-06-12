namespace tbm.Crawler
{
    public class ReplySaver : BaseSaver<ReplyPost>
    {
        public override FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance { get; } = new(
            Update: (whichPostType, propertyName, originalValue, currentValue) =>
                // the value of IconInfo in response of reply crawler might be null even if the user haven't change his icon info
                propertyName == nameof(TiebaUser.IconInfo) && currentValue is null,
            Revision: (whichPostType, propertyName, originalValue, currentValue) =>
            {
                switch (propertyName)
                { // the value of user gender returned by thread saver is always null
                    case nameof(TiebaUser.Gender) when originalValue is null:
                    // the value of IconInfo returned by thread saver is always null since we ignored its value
                    case nameof(TiebaUser.IconInfo) when originalValue is null:
                        return true;
                }
                return false;
            });

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
