namespace tbm.Crawler.Tieba.Crawl.Facade
{
    public class ThreadCrawlFacade : BaseCrawlFacade<ThreadPost, BaseThreadRevision, ThreadResponse, Thread, ThreadCrawler>
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

            // users has already been added into DbContext and tracking
            var existingUsersId = db.ChangeTracker.Entries<TiebaUser>().Select(e => e.Entity.Uid);

            var newLatestRepliers = _latestRepliers
                .ExceptBy(existingUsersId, i => i.Key)
                .Select(i => i.Value).ToList();
            if (!newLatestRepliers.Any()) return;

            var newLatestRepliersExceptLocked = newLatestRepliers
                .IntersectBy(Users.AcquireUidLocksForSave(
                    newLatestRepliers.Select(u => u.Uid)), u => u.Uid)
                .Select(u =>
                {
                    u.CreatedAt = Helper.GetNowTimestamp();
                    return u;
                });
            _ = db.Users.UpsertRange(newLatestRepliersExceptLocked).NoUpdate().Run();
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

        protected void FillDetailedGeolocation(IEnumerable<Thread> threads) =>
            threads // replace with more detailed location.name in the 6.0.2 response
                .Where(t => t.Location != null)
                .Join(Posts.Values, i => (Tid)i.Tid, i => i.Tid,
                    (inResponse, parsed) => (inResponse, parsed))
                .ForEach(tuple => tuple.parsed.Geolocation =
                    Helper.SerializedProtoBufOrNullIfEmpty(tuple.inResponse.Location));

        protected override void PostParseHook(ThreadResponse response, CrawlRequestFlag flag, Dictionary<PostId, ThreadPost> parsedPostsInResponse)
        {
            var data = response.Data;
            if (flag == CrawlRequestFlag.ThreadClientVersion602) FillDetailedGeolocation(data.ThreadList);
            if (flag != CrawlRequestFlag.None) return;
            Users.ParseUsers(data.UserList);
            Users.ResetUsersIcon();
            ParseLatestRepliers(data.ThreadList);
            // remove livepost threads since their real parent forum may not match with current crawling fid
            data.ThreadList.Where(t => t.LivePostType != "")
                .ForEach(t => Posts.TryRemove((Tid)t.Tid, out _));
        }
    }
}
