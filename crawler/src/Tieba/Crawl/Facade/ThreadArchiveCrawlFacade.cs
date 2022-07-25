namespace tbm.Crawler
{
    public class ThreadArchiveCrawlFacade : ThreadCrawlFacade
    {
        public new delegate ThreadArchiveCrawlFacade New(Fid fid, string forumName);

        public ThreadArchiveCrawlFacade(ILogger<ThreadCrawlFacade> logger, TbmDbContext.New dbContextFactory,
            ThreadArchiveCrawler.New crawler, ThreadParser parser, ThreadSaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks.New> locks, uint fid, string forumName
        ) : base(logger, dbContextFactory, crawler.Invoke, parser, saver, users, requesterTcs, locks, fid, forumName) { }

        protected override void PostParseCallback(ThreadResponse response, CrawlRequestFlag flag)
        {
            // the second response with flag is as same as the first one so just skip it
            if (flag == CrawlRequestFlag.Thread602ClientVersion) return;
            var data = response.Data;
            // parsed author uid will be 0 when request with client version 6.0.2
            ParsedPosts.Values.ForEach(t => t.AuthorUid = data.ThreadList.First(t2 => (Tid)t2.Tid == t.Tid).Author.Uid);
            Users.ParseUsers(data.ThreadList.Select(t => t.Author));
            ParseLatestRepliers(data);
        }
    }
}
