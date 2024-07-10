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

            // LastReplyer will be null when LivePostType != "", but LastTimeInt will have expected timestamp value
            t.parsed.LatestReplierUid = t.inResponse.LastReplyer?.Uid;
        });
}
