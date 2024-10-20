namespace tbm.Crawler.Tieba.Crawl.Facade;

public class ThreadArchiveCrawlFacade(
    ThreadArchiveCrawler.New crawlerFactory,
    string forumName,
    Fid fid,
    IIndex<CrawlerLocks.Type, CrawlerLocks> locks,
    ThreadParser postParser,
    ThreadSaver.New postSaverFactory,
    UserParser.New userParserFactory,
    UserSaver.New userSaverFactory)
    : ThreadCrawlFacade(
        crawlerFactory.Invoke, forumName, fid, locks,
        postParser, postSaverFactory,
        userParserFactory.Invoke, userSaverFactory.Invoke)
{
    public new delegate ThreadArchiveCrawlFacade New(Fid fid, string forumName);

    protected override void OnPostParse(
        ThreadResponse response,
        CrawlRequestFlag flag,
        IReadOnlyDictionary<PostId, ThreadPost.Parsed> parsedPosts)
    { // the second respond with flag is as same as the first one so just skip it
        if (flag == CrawlRequestFlag.ThreadClientVersion602) return;
        var data = response.Data;
        UserParser.Parse(data.ThreadList.Select(th => th.Author));
        FillFromRequestingWith602(data.ThreadList);

        // parsed author uid will be 0 when request with client version 6.0.2
        (from parsed in parsedPosts.Values
                join newInResponse in data.ThreadList on parsed.Tid equals (Tid)newInResponse.Tid
                select (parsed, newInResponse))
            .ForEach(t => t.parsed.AuthorUid = t.newInResponse.Author.Uid);
    }
}
