namespace tbm.Crawler
{
    public class SubReplyCrawlFacade : BaseCrawlFacade<SubReplyPost, SubReplyResponse, SubReply, SubReplyCrawler>
    {
        private readonly Tid _tid;
        private readonly Pid _pid;

        public delegate SubReplyCrawlFacade New(Fid fid, Tid tid, Pid pid);

        public SubReplyCrawlFacade(ILogger<SubReplyCrawlFacade> logger, TbmDbContext.New dbContextFactory,
            SubReplyCrawler.New crawler, SubReplyParser parser, SubReplySaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks> locks, Fid fid, Tid tid, Pid pid
        ) : base(logger, dbContextFactory, crawler(tid, pid), parser, saver.Invoke, users, requesterTcs, (locks["subReply"], new (fid, tid, pid)), fid)
        {
            _tid = tid;
            _pid = pid;
        }

        protected override void PostParseHook(SubReplyResponse response, CrawlRequestFlag flag) =>
            ParsedPosts.Values.ForEach(sr =>
            {
                sr.Tid = _tid;
                sr.Pid = _pid;
            });
    }
}
