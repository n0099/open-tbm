namespace tbm.Crawler.Tieba.Crawl.Facade;

public class ThreadCrawlFacade(
        ThreadCrawler.New crawlerFactory,
        string forumName,
        Fid fid,
        IIndex<CrawlerLocks.Type, CrawlerLocks> locks,
        ThreadParser postParser,
        ThreadSaver.New postSaverFactory,
        UserParser.New userParserFactory,
        UserSaver.New userSaverFactory)
    : CrawlFacade<ThreadPost, ThreadResponse, Thread>(
        crawlerFactory(forumName), fid, new(fid), locks[CrawlerLocks.Type.Thread],
        postParser, postSaverFactory.Invoke,
        userParserFactory.Invoke, userSaverFactory.Invoke)
{
    private readonly Dictionary<Uid, User> _latestRepliers = [];

    public delegate ThreadCrawlFacade New(Fid fid, string forumName);

    protected override void OnBeforeCommitSave(CrawlerDbContext db, UserSaver userSaver)
    { // OnBeforeCommitSave() should get invoked after UserSaver.Save() by the base.SaveCrawled()
        // so only latest repliers that not exists in parsed users are being inserted
        // note this will bypass user revision detection since not invoking SaverWithRevision.SaveEntitiesWithRevision() but directly DbContext.AddRange()

        // users has already been added into DbContext and tracking
        var existingUsersId = db.ChangeTracker.Entries<User>().Select(ee => ee.Entity.Uid);
        var newLatestRepliers = _latestRepliers
            .ExceptBy(existingUsersId, pair => pair.Key)
            .Select(pair => pair.Value)
            .ToList();
        if (newLatestRepliers.Count == 0) return;

        var newlyLockedLatestRepliers = userSaver.AcquireUidLocksForSave
            (newLatestRepliers.Select(u => u.Uid));
        var newLatestRepliersExceptLocked = newLatestRepliers
            .IntersectBy(newlyLockedLatestRepliers, u => u.Uid)
            .Select(u =>
            {
                u.CreatedAt = SharedHelper.GetNowTimestamp();
                return u;
            });
        db.Users.AddRange(newLatestRepliersExceptLocked);
    }

    protected override void OnPostParse(
        ThreadResponse response,
        CrawlRequestFlag flag,
        IReadOnlyDictionary<PostId, ThreadPost> parsedPostsInResponse)
    {
        var data = response.Data;
        if (flag == CrawlRequestFlag.ThreadClientVersion602) FillFromRequestingWith602(data.ThreadList);
        if (flag != CrawlRequestFlag.None) return;
        UserParser.Parse(data.UserList);
        UserParser.ResetUsersIcon();
        ParseLatestRepliers(data.ThreadList);

        // remove livepost threads since their real parent forum may not match with current crawling fid
        data.ThreadList.Where(th => th.LivePostType != "")
            .ForEach(th => Posts.TryRemove((Tid)th.Tid, out _));
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
}
