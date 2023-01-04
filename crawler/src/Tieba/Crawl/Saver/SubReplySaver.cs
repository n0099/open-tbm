using LinqToDB;

namespace tbm.Crawler.Tieba.Crawl.Saver
{
    public class SubReplySaver : BaseSaver<SubReplyPost>
    {
        public override FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance { get; } = new(
            Update: (_, propName, oldValue, newValue) => propName switch
            { // always ignore updates on iconinfo due to some rare user will show some extra icons
                // compare to reply response in the response of sub reply
                nameof(TiebaUser.Icon) => true,
                // FansNickname in sub reply response will always be null
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
            var changeSet = SavePosts(db, sr => sr.Spid, r => (long)r.Spid,
                sr => new SubReplyRevision {Time = sr.UpdatedAt ?? sr.CreatedAt, Spid = sr.Spid},
                PredicateBuilder.New<SubReplyPost>(sr => Posts.Keys.Contains(sr.Spid)),
                newRevisions => existing => newRevisions.Select(r => r.Spid).Contains(existing.Spid),
                r => new() {Time = r.Time, Spid = r.Spid});

            db.SubReplyContents.AddRange(changeSet.NewlyAdded.Select(sr => new SubReplyContent {Spid = sr.Spid, Content = sr.Content}));

            // prepare and reuse this timestamp for consistency in current saving
            var now = (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
            SaveAuthorRevisions(db.Fid, db,
                db.AuthorExpGradeRevisions,
                p => p.AuthorExpGrade,
                (a, b) => a != b,
                r => new()
                {
                    Uid = r.Uid,
                    Value = r.AuthorExpGrade,
                    Rank = Sql.Ext.Rank().Over().PartitionBy(r.Uid).OrderByDesc(r.Time).ToValue()
                },
                tuple => new()
                {
                    Time = now,
                    Fid = db.Fid,
                    Uid = tuple.Uid,
                    AuthorExpGrade = tuple.Value
                });

            return changeSet;
        }
    }
}
