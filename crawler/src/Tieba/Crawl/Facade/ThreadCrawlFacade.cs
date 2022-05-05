namespace tbm.Crawler
{
    public class ThreadCrawlFacade : BaseCrawlFacade<ThreadPost, ThreadResponse, Thread, ThreadCrawler>
    {
        public delegate ThreadCrawlFacade New(Fid fid, string forumName);

        public ThreadCrawlFacade(ILogger<ThreadCrawlFacade> logger, ThreadCrawler.New crawler,
            ThreadParser parser, ThreadSaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks.New> locks, Fid fid, string forumName
        ) : base(logger, crawler(forumName), parser, saver.Invoke, users, requesterTcs, (locks["thread"]("thread"), fid), fid) { }

        protected override void PostParseCallback((ThreadResponse, CrawlRequestFlag) responseAndFlag, IList<Thread> posts)
        {
            var (response, flag) = responseAndFlag;
            if (flag == CrawlRequestFlag.Thread602ClientVersion) return;
            Users.ParseUsers(response.Data.UserList);
        }
    }
}
