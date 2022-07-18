namespace tbm.Crawler
{
    public class ThreadCrawlFacade : BaseCrawlFacade<ThreadPost, ThreadResponse, Thread, ThreadCrawler>
    {
        public delegate ThreadCrawlFacade New(Fid fid, string forumName);

        public ThreadCrawlFacade(ILogger<ThreadCrawlFacade> logger, TbmDbContext.New dbContextFactory,
            ThreadCrawler.New crawler, ThreadParser parser, ThreadSaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks.New> locks, Fid fid, string forumName
        ) : base(logger, dbContextFactory, crawler(forumName), parser, saver.Invoke, users, requesterTcs, (locks["thread"]("thread"), fid), fid) { }

        protected override void PostParseCallback(ThreadResponse response, CrawlRequestFlag flag)
        {
            var data = response.Data;
            if (flag == CrawlRequestFlag.Thread602ClientVersion) return;
            var users = data.UserList;
            Users.ParseUsers(users);

            ParsedPosts.Values.IntersectBy(data.ThreadList.Select(p => (Tid)p.Tid), p => p.Tid)
                .Where(t => t.StickyType == null).ForEach(p =>
                p.AuthorManagerType = users.First(u => u.Uid == p.AuthorUid).BawuType.NullIfWhiteSpace());
        }
    }
}
