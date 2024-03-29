namespace tbm.Crawler.Tieba.Crawl.Facade;

public class ThreadCrawlFacade(
        ThreadCrawler.New crawler,
        ThreadParser parser,
        ThreadSaver.New saver,
        IIndex<string, CrawlerLocks> locks,
        Fid fid,
        string forumName)
    : BaseCrawlFacade<ThreadPost, BaseThreadRevision, ThreadResponse, Thread>
        (crawler(forumName), parser, saver.Invoke, locks["thread"], new(fid), fid)
{
    private readonly Dictionary<long, User> _latestRepliers = [];

    public delegate ThreadCrawlFacade New(Fid fid, string forumName);

    protected override void BeforeCommitSaveHook(CrawlerDbContext db)
    { // BeforeCommitSaveHook() should get invoked after UserParserAndSaver.SaveUsers() by the base.SaveCrawled()
        // so only latest repliers that not exists in parsed users are being inserted
        // note this will bypass user revision detection since not invoking CommonInSavers.SavePostsOrUsers() but directly DbContext.AddRange()

        // users has already been added into DbContext and tracking
        var existingUsersId = db.ChangeTracker.Entries<User>().Select(ee => ee.Entity.Uid);
        var newLatestRepliers = _latestRepliers
            .ExceptBy(existingUsersId, pair => pair.Key)
            .Select(pair => pair.Value)
            .ToList();
        if (newLatestRepliers.Count == 0) return;

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

    protected void ParseLatestRepliers(IEnumerable<Thread> threads) =>
        threads.Select(th => th.LastReplyer ?? null) // LastReplyer will be null when LivePostType != ""
            .OfType<TbClient.User>() // filter out nulls

            // some rare deleted thread but still visible in 6.0.2 response
            // will have the latest replier uid=0 name="" nameShow=".*"
            .Where(u => u.Uid != 0)
            .Select(u => User.CreateLatestReplier(u.Uid, u.Name.NullIfEmpty(),
                u.Name == u.NameShow ? null : u.NameShow))
            .ForEach(u => _latestRepliers[u.Uid] = u);

    protected void FillFromRequestingWith602(IEnumerable<Thread> threads) =>
        (from inResponse in threads
            join parsed in Posts.Values on (Tid)inResponse.Tid equals parsed.Tid
            select (inResponse, parsed))
        .ForEach(t =>
        {
            if (t.inResponse.Location != null)
            { // replace with more detailed location.name in the 6.0.2 response
                t.parsed.Geolocation = Helper.SerializedProtoBufOrNullIfEmpty(t.inResponse.Location);
            }

            // LastReplyer will be null when LivePostType != "", but LastTimeInt will have expected timestamp value
            t.parsed.LatestReplierUid = t.inResponse.LastReplyer?.Uid;
        });

    protected override void PostParseHook(
        ThreadResponse response,
        CrawlRequestFlag flag,
        IDictionary<PostId, ThreadPost> parsedPostsInResponse)
    {
        var data = response.Data;
        if (flag == CrawlRequestFlag.ThreadClientVersion602) FillFromRequestingWith602(data.ThreadList);
        if (flag != CrawlRequestFlag.None) return;
        Users.ParseUsers(data.UserList);
        Users.ResetUsersIcon();
        ParseLatestRepliers(data.ThreadList);

        // remove livepost threads since their real parent forum may not match with current crawling fid
        data.ThreadList.Where(th => th.LivePostType != "")
            .ForEach(th => Posts.TryRemove((Tid)th.Tid, out _));
    }
}
