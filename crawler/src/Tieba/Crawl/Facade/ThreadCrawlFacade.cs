namespace tbm.Crawler.Tieba.Crawl.Facade;

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
        var existingUsersId = db.ChangeTracker.Entries<TiebaUser>().Select(ee => ee.Entity.Uid);
        var newLatestRepliers = _latestRepliers
            .ExceptBy(existingUsersId, pair => pair.Key)
            .Select(pair => pair.Value).ToList();
        if (!newLatestRepliers.Any()) return;

        var newlyLockedLatestRepliers = Users.AcquireUidLocksForSave(newLatestRepliers.Select(u => u.Uid));
        var newLatestRepliersExceptLocked = newLatestRepliers
            .IntersectBy(newlyLockedLatestRepliers, u => u.Uid)
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
        threads.Select(th => th.LastReplyer ?? null) // LastReplyer will be null when LivePostType != ""
            .OfType<User>() // filter out nulls
            .Where(u => u.Uid != 0) // some rare deleted thread but still visible in 6.0.2 response will have a latest replier uid=0 name="" nameShow=".*"
            .Select(u => LatestReplierFactory(u.Uid, u.Name.NullIfWhiteSpace(),
                u.Name == u.NameShow ? null : u.NameShow))
            .ForEach(u => _latestRepliers[u.Uid] = u);

    protected void FillDetailedGeolocation(IEnumerable<Thread> threads) =>
        (from inResponse in threads
            where inResponse.Location != null
            join parsed in Posts.Values on (Tid)inResponse.Tid equals parsed.Tid
            select (inResponse, parsed))
        // replace with more detailed location.name in the 6.0.2 response
        .ForEach(t => t.parsed.Geolocation = Helper.SerializedProtoBufOrNullIfEmpty(t.inResponse.Location));

    protected override void PostParseHook(ThreadResponse response, CrawlRequestFlag flag, Dictionary<PostId, ThreadPost> parsedPostsInResponse)
    {
        var data = response.Data;
        if (flag == CrawlRequestFlag.ThreadClientVersion602) FillDetailedGeolocation(data.ThreadList);
        if (flag != CrawlRequestFlag.None) return;
        Users.ParseUsers(data.UserList);
        Users.ResetUsersIcon();
        ParseLatestRepliers(data.ThreadList);
        // remove livepost threads since their real parent forum may not match with current crawling fid
        data.ThreadList.Where(th => th.LivePostType != "")
            .ForEach(th => Posts.TryRemove((Tid)th.Tid, out _));
    }
}
