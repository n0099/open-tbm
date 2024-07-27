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
    : CrawlFacade<ThreadPost, ThreadPost.Parsed, ThreadResponse, Thread>(
        crawlerFactory(forumName), fid, new(fid), locks[CrawlerLocks.Type.Thread],
        postParser, postSaverFactory.Invoke,
        userParserFactory.Invoke, userSaverFactory.Invoke)
{
    private readonly Dictionary<ThreadLatestReplierSaver.UniqueLatestReplier, LatestReplier?> _latestRepliersKeyByUnique = [];

    public delegate ThreadCrawlFacade New(Fid fid, string forumName);

    protected override void OnPostParse(
        ThreadResponse response,
        CrawlRequestFlag flag,
        IReadOnlyDictionary<PostId, ThreadPost.Parsed> parsedPosts)
    {
        var data = response.Data;
        if (flag == CrawlRequestFlag.ThreadClientVersion602) FillFromRequestingWith602(data.ThreadList);
        if (flag != CrawlRequestFlag.None) return;
        UserParser.Parse(data.UserList);
        UserParser.ResetUsersIcon();

        // remove livepost threads since their real parent forum may not match with current crawling fid
        data.ThreadList.Where(th => th.LivePostType != "")
            .ForEach(th => Posts.TryRemove((Tid)th.Tid, out _));
    }

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
            var name = t.inResponse.LastReplyer.Name.NullIfEmpty();
            var nameShow = t.inResponse.LastReplyer.NameShow.NullIfEmpty();

            // LastReplyer will be null when LivePostType != "", but LastTimeInt will have expected timestamp value
            var latestReplierEntity = t.inResponse.LastReplyer == null ? null : new LatestReplier
            {
                Name = name,
#pragma warning disable S3358 // Ternary operators should not be nested
                DisplayName = name == nameShow ? null : nameShow
#pragma warning restore S3358 // Ternary operators should not be nested
            };
            var uniqueLatestReplier = ThreadLatestReplierSaver.UniqueLatestReplier.FromLatestReplier(latestReplierEntity);

            var isExists = _latestRepliersKeyByUnique.TryGetValue(uniqueLatestReplier, out var existingLatestReplier);
            if (!isExists) _latestRepliersKeyByUnique[uniqueLatestReplier] = latestReplierEntity;
            t.parsed.LatestReplier = isExists ? existingLatestReplier : latestReplierEntity;
        });
}
