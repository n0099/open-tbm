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
            // so only latest repliers that not exists in parsed users is being inserted
            // note this will bypass user revision detection since not invoking CommonInSavers.SavePostsOrUsers() but directly DbContext.AddRange()
            var existingUsersId = (from u in db.Users
                where _latestRepliers.Keys.Any(uid => uid == u.Uid)
                select u.Uid).ToHashSet();
            existingUsersId.UnionWith( // users not exists in db but have already been added and tracking
                db.ChangeTracker.Entries<TiebaUser>().Select(e => e.Entity.Uid));

            var newLatestRepliers = _latestRepliers
                .ExceptBy(existingUsersId, i => i.Key)
                .Select(i => i.Value).ToList();
            if (!newLatestRepliers.Any()) return;
            db.AddRange(newLatestRepliers.IntersectBy(
                Users.AcquireUidLockForSave(newLatestRepliers.Select(u => u.Uid)),
                u => u.Uid));
        }

        public static TiebaUser LatestReplierFactory(long uid, string? name, string? displayName) =>
            new() {Uid = uid, Name = name, DisplayName = displayName};

        protected void ParseLatestRepliers(ThreadResponse.Types.Data data) =>
            data.ThreadList
                .Select(t => t.LastReplyer)
                .Where(u => u.Uid != 0) // some rare deleted thread but still visible in 6.0.2 response will have a latest replier uid=0 name="" nameShow=".*"
                .Select(u =>
                    LatestReplierFactory(u.Uid, u.Name.NullIfWhiteSpace(), u.Name == u.NameShow ? null : u.NameShow))
                .ForEach(u => _latestRepliers[u.Uid] = u);

        protected override void ThrowIfEmptyUsersEmbedInPosts() =>
            throw new TiebaException($"User list in response of thread list for fid {Fid} is empty.");

        protected override void ParsePostsEmbeddedUsers(List<User> usersEmbedInPosts, IList<Thread> postsInCurrentResponse) =>
            ParsedPosts.Values
                .IntersectBy(postsInCurrentResponse.Select(t => (Tid)t.Tid), t => t.Tid) // only mutate posts which occurs in current response
                .Where(t => t.StickyType == null) // manager type for the author of sticky threads will be default empty string
                .ForEach(t => // fill the values of author manager type from the external user list
                    t.AuthorManagerType = usersEmbedInPosts.First(u => u.Uid == t.AuthorUid).BawuType.NullIfWhiteSpace());

        protected override void PostParseHook(ThreadResponse response, CrawlRequestFlag flag)
        {
            if (flag == CrawlRequestFlag.None)
            {
                var data = response.Data;
                ParseLatestRepliers(data);
                data.ThreadList.Where(t => t.Fid != Fid) // thread with mismatch parent fid might be multi forum or livepost
                    .ForEach(t => ParsedPosts.TryRemove((Tid)t.Tid, out _));
            }
        }
    }
}
