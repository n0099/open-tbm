namespace tbm.Crawler
{
    public class SubReplySaver : BaseSaver<SubReplyPost>
    {
        public override FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance { get; } = new(
            Update: (_, propertyName, originalValue, currentValue) => propertyName switch
            { // always ignore updates on iconinfo due to some rare user will show some extra icons
                // compare to reply response in the response of sub reply
                nameof(TiebaUser.IconInfo) => true,
                // fans nick name within sub reply response will always be null
                nameof(TiebaUser.FansNickname) when originalValue is not null && currentValue is null => true,
                _ => false
            }, (_, _, _, _) => false);

        protected override Dictionary<string, ushort> RevisionNullFieldsBitMasks { get; } = new()
        {
            {nameof(SubReplyPost.AuthorManagerType), 1},
            {nameof(SubReplyPost.AgreeNum),          1 << 1},
            {nameof(SubReplyPost.DisagreeNum),       1 << 2}
        };

        private readonly Fid _fid;

        public delegate SubReplySaver New(ConcurrentDictionary<PostId, SubReplyPost> posts, Fid fid);

        public SubReplySaver(ILogger<SubReplySaver> logger, ConcurrentDictionary<PostId, SubReplyPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override SaverChangeSet<SubReplyPost> SavePosts(TbmDbContext db)
        {
            var changeSet = SavePosts(db, sr => sr.Spid,
                PredicateBuilder.New<SubReplyPost>(sr => Posts.Keys.Contains(sr.Spid)),
                sr => new SubReplyRevision {Time = sr.UpdatedAt ?? sr.CreatedAt, Spid = sr.Spid});

            db.SubReplyContents.AddRange(changeSet.NewlyAdded.Select(sr => new SubReplyContent {Spid = sr.Spid, Content = sr.Content}));
            return changeSet;
        }
    }
}
