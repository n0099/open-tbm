namespace tbm.Crawler.Tieba.Crawl.Saver
{
    public class SubReplySaver : BaseSaver<SubReplyPost>
    {
        public override FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance { get; } = new(
            Update: (_, propName, oldValue, newValue) => propName switch
            { // always ignore updates on iconinfo due to some rare user will show some extra icons
                // compare to reply response in the response of sub reply
                nameof(TiebaUser.Icon) => true,
                // fans nick name within sub reply response will always be null
                nameof(TiebaUser.FansNickname) when oldValue is not null && newValue is null => true,
                // DisplayName in users embedded in sub replies from response will be the legacy nick name
                nameof(TiebaUser.DisplayName) => true,
                _ => false
            },
            Revision: (_, propName, oldValue, newValue) => propName switch
            {
                // author gender in reply response will be 0 when users is embed in reply
                // but in thread or sub reply responses it won't be 0 even their users are also embedded
                nameof(TiebaUser.Gender) when (ushort?)oldValue is 0 && (ushort?)newValue is not 0 => true,
                _ => false
            });

        protected override Dictionary<string, ushort> RevisionNullFieldsBitMasks { get; } = new()
        {
            {nameof(SubReplyPost.AuthorManagerType), 1},
            {nameof(SubReplyPost.AgreeCount),        1 << 1},
            {nameof(SubReplyPost.DisagreeCount),     1 << 2}
        };

        public delegate SubReplySaver New(ConcurrentDictionary<PostId, SubReplyPost> posts);

        public SubReplySaver(ILogger<SubReplySaver> logger, ConcurrentDictionary<PostId, SubReplyPost> posts) : base(logger, posts) { }

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
