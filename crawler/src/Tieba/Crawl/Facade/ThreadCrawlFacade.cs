namespace tbm.Crawler
{
    public class ThreadCrawlFacade : BaseCrawlFacade<ThreadPost, ThreadResponse, Thread, ThreadCrawler>
    {
        private readonly Dictionary<long, TiebaUser> _latestRepliers = new();
        public delegate ThreadCrawlFacade New(Fid fid, string forumName);

        public ThreadCrawlFacade(ILogger<ThreadCrawlFacade> logger, TbmDbContext.New dbContextFactory,
            ThreadCrawler.New crawler, ThreadParser parser, ThreadSaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks> locks, Fid fid, string forumName
        ) : base(logger, dbContextFactory, crawler(forumName), parser, saver.Invoke, users, requesterTcs, (locks["thread"], new (fid)), fid) { }

        protected override void ExtraSavings(TbmDbContext db)
        { // ExtraSavings() should get invoked after UserParserAndSaver.SaveUsers() by the base.SaveAll()
            // so only users that not exists in parsed users is being inserted
            // note this will bypass user revision detection since its not invoking CommonInSavers.SavePostsOrUsers()
            var existingUsersId = (from u in db.Users where _latestRepliers.Keys.Any(uid => uid == u.Uid) select u.Uid).ToHashSet();
            existingUsersId.UnionWith(db.ChangeTracker.Entries<TiebaUser>().Select(e => e.Entity.Uid)); // users not exists in db but have already been added and tracking
            var newLatestRepliers = _latestRepliers.ExceptBy(existingUsersId, i => i.Key).Select(i => i.Value).ToList();

            if (!newLatestRepliers.Any()) return;
            db.AddRange(newLatestRepliers.IntersectBy(
                Users.AcquireUidLock(newLatestRepliers.Select(u => u.Uid)),
                u => u.Uid));
        }

        public static TiebaUser LatestReplierFactory(long uid, string? name, string? displayName) =>
            new() {Uid = uid, Name = name, DisplayName = displayName};

        protected void ParseLatestRepliers(ThreadResponse.Types.Data data) =>
            // some rare deleted thread but still visible in 6.0.2 response will have a latest replier uid=0 name="" nameShow=".*"
            data.ThreadList.Select(t => t.LastReplyer).Where(u => u.Uid != 0).Select(u =>
                LatestReplierFactory(u.Uid, u.Name.NullIfWhiteSpace(), u.Name == u.NameShow ? null : u.NameShow))
                .ForEach(u => _latestRepliers[u.Uid] = u);

        protected override void PostParseCallback(ThreadResponse response, CrawlRequestFlag flag)
        {
            var data = response.Data;
            if (flag == CrawlRequestFlag.Thread602ClientVersion)
            {
                ParseLatestRepliers(data);
                return;
            }
            var users = data.UserList;
            if (!users.Any()) return;
            Users.ParseUsers(users);

            ParsedPosts.Values.IntersectBy(data.ThreadList.Select(t => (Tid)t.Tid), t => t.Tid)
                .Where(t => t.StickyType == null).ForEach(t =>
                    // fill the values of author manager type from the external user list
                    t.AuthorManagerType = users.First(u => u.Uid == t.AuthorUid).BawuType.NullIfWhiteSpace());
        }
    }
}
