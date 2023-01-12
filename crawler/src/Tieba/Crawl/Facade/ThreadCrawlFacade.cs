namespace tbm.Crawler.Tieba.Crawl.Facade
{
    public class ThreadCrawlFacade : BaseCrawlFacade<ThreadPost, ThreadResponse, Thread, ThreadCrawler>
    {
        private readonly Dictionary<long, TiebaUser> _latestRepliers = new();

        public delegate ThreadCrawlFacade New(Fid fid, string forumName);

        public ThreadCrawlFacade(ILogger<ThreadCrawlFacade> logger, TbmDbContext.New dbContextFactory,
            ThreadCrawler.New crawler, ThreadParser parser, ThreadSaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks> locks, Fid fid, string forumName
        ) : base(logger, dbContextFactory, crawler(forumName), parser, saver.Invoke, users, requesterTcs, (locks["thread"], new (fid)), fid) { }

        protected override void BeforeCommitSaveHook(TbmDbContext db)
        { // BeforeCommitSaveHook() should get invoked after UserParserAndSaver.SaveUsers() by the base.SaveCrawled()
            // so only latest repliers that not exists in parsed users are being inserted
            // note this will bypass user revision detection since not invoking CommonInSavers.SavePostsOrUsers() but directly DbContext.AddRange()
            var existingUsersId = (from u in db.Users.AsNoTracking()
                where _latestRepliers.Keys.Any(uid => uid == u.Uid)
                select u.Uid).ToHashSet();
            existingUsersId.UnionWith( // users not exists in DB but has already been added into DbContext and tracking
                db.ChangeTracker.Entries<TiebaUser>().Select(e => e.Entity.Uid));

            var newLatestRepliers = _latestRepliers
                .ExceptBy(existingUsersId, i => i.Key)
                .Select(i => i.Value).ToList();
            if (!newLatestRepliers.Any()) return;

            var newLatestRepliersExceptLocked = newLatestRepliers
                .IntersectBy(Users.AcquireUidLocksForSave(
                    newLatestRepliers.Select(u => u.Uid)), u => u.Uid)
                .ToList();
            db.Users.AddRange(newLatestRepliersExceptLocked);
            db.TimestampingEntities(newLatestRepliersExceptLocked);
        }

        public static TiebaUser LatestReplierFactory(long uid, string? name, string? displayName) =>
            new() {Uid = uid, Name = name, DisplayName = displayName};

        protected void ParseLatestRepliers(IEnumerable<Thread> threads) =>
            threads.Select(t => t.LastReplyer ?? null) // LastReplyer will be null when LivePostType != ""
                .OfType<User>() // filter out nulls
                .Where(u => u.Uid != 0) // some rare deleted thread but still visible in 6.0.2 response will have a latest replier uid=0 name="" nameShow=".*"
                .Select(u =>
                    LatestReplierFactory(u.Uid, u.Name.NullIfWhiteSpace(), u.Name == u.NameShow ? null : u.NameShow))
                .ForEach(u => _latestRepliers[u.Uid] = u);

        protected override void PostParseHook(ThreadResponse response, CrawlRequestFlag flag)
        {
            if (flag != CrawlRequestFlag.None) return;
            var data = response.Data;
            Users.ParseUsers(data.UserList);
            Users.ResetUsersIcon();
            ParseLatestRepliers(data.ThreadList);
            // remove livepost threads since their real parent forum may not match with current crawling fid
            data.ThreadList.Where(t => t.LivePostType != "")
                .ForEach(t => ParsedPosts.TryRemove((Tid)t.Tid, out _));
        }
    }
}
