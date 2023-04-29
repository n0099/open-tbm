namespace tbm.Crawler.Tieba.Crawl.Facade;

public class ThreadArchiveCrawlFacade : ThreadCrawlFacade
{
    public new delegate ThreadArchiveCrawlFacade New(Fid fid, string forumName);

    public ThreadArchiveCrawlFacade(ILogger<ThreadArchiveCrawlFacade> logger, CrawlerDbContext.New dbContextFactory,
        ThreadArchiveCrawler.New crawler, ThreadParser parser, ThreadSaver.New saver, UserParserAndSaver users,
        ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks> locks, Fid fid, string forumName
    ) : base(logger, dbContextFactory, crawler.Invoke, parser, saver, users, requesterTcs, locks, fid, forumName) { }

    protected override void PostParseHook(ThreadResponse response, CrawlRequestFlag flag, Dictionary<PostId, ThreadPost> parsedPostsInResponse)
    { // the second respond with flag is as same as the first one so just skip it
        if (flag == CrawlRequestFlag.ThreadClientVersion602) return;
        var data = response.Data;
        Users.ParseUsers(data.ThreadList.Select(th => th.Author));
        ParseLatestRepliers(data.ThreadList);
        FillDetailedGeolocation(data.ThreadList);

        // parsed author uid will be 0 when request with client version 6.0.2
        (from parsed in parsedPostsInResponse.Values
                join newInResponse in data.ThreadList on parsed.Tid equals (Tid)newInResponse.Tid
                select (parsed, newInResponse))
            .ForEach(t => t.parsed.AuthorUid = t.newInResponse.Author.Uid);
    }
}
