namespace tbm.Crawler
{
    public class SubReplySaver : BaseSaver<SubReplyPost>
    {
        public override FieldsChangeIgnoranceWrapper TiebaUserFieldsChangeIgnorance { get; } = new(new(),
            Revision: new()
            { // the value of IconInfo returned by thread saver is always null since we ignored its value
                [typeof(TiebaUser)] = new() {new(nameof(TiebaUser.IconInfo), true)}
            });

        private readonly Fid _fid;

        public delegate SubReplySaver New(ConcurrentDictionary<PostId, SubReplyPost> posts, Fid fid);

        public SubReplySaver(ILogger<SubReplySaver> logger, ConcurrentDictionary<PostId, SubReplyPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override SaverChangeSet<SubReplyPost> SavePosts(TbmDbContext db) => SavePosts(db,
            PredicateBuilder.New<SubReplyPost>(p => Posts.Keys.Any(id => id == p.Spid)),
            PredicateBuilder.New<PostIndex>(i => i.Type == "reply" && Posts.Keys.Any(id => id == i.Spid)),
            p => p.Spid,
            i => i.Spid,
            p => new() {Type = "reply", Fid = _fid, Tid = p.Tid, Pid = p.Pid, Spid = p.Spid, PostTime = p.PostTime},
            p => new SubReplyRevision {Time = p.UpdatedAt, Spid = p.Spid},
            () => new SubReplyRevisionNullFields());
    }
}
