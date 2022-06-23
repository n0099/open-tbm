namespace tbm.Crawler
{
    public class SubReplySaver : BaseSaver<SubReplyPost>
    {
        public override FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance { get; } = new(
            Update: (_, propertyName, originalValue, currentValue) =>
                propertyName switch
                { // always ignore updates on iconinfo due to some rare user will show some extra icons
                    // compare to reply response in the response of sub reply
                    nameof(TiebaUser.IconInfo) => true,
                    // fans nick name within sub reply response will always be null
                    nameof(TiebaUser.FansNickname) when originalValue is not null && currentValue is null => true,
                    _ => false
                },
            (_, _, _, _) => false);

        private readonly Fid _fid;

        public delegate SubReplySaver New(ConcurrentDictionary<PostId, SubReplyPost> posts, Fid fid);

        public SubReplySaver(ILogger<SubReplySaver> logger, ConcurrentDictionary<PostId, SubReplyPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override SaverChangeSet<SubReplyPost> SavePosts(TbmDbContext db) => SavePosts(db,
            PredicateBuilder.New<SubReplyPost>(p => Posts.Keys.Any(id => id == p.Spid)),
            PredicateBuilder.New<PostIndex>(i => i.Type == "reply" && Posts.Keys.Any(id => id == i.Spid!.Value)),
            p => p.Spid,
            i => i.Spid!.Value,
            p => new() {Type = "reply", Fid = _fid, Tid = p.Tid, Pid = p.Pid, Spid = p.Spid, PostTime = p.PostTime},
            p => new SubReplyRevision {Time = p.UpdatedAt, Spid = p.Spid},
            () => new SubReplyRevisionNullFields());
    }
}
