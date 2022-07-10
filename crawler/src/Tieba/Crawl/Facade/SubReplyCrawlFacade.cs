namespace tbm.Crawler
{
    public class SubReplyCrawlFacade : BaseCrawlFacade<SubReplyPost, SubReplyResponse, SubReply, SubReplyCrawler>
    {
        private readonly Tid _tid;
        private readonly Pid _pid;

        public delegate SubReplyCrawlFacade New(Fid fid, Tid tid, Pid pid);

        public SubReplyCrawlFacade(ILogger<SubReplyCrawlFacade> logger, TbmDbContext.New dbContextFactory,
            SubReplyCrawler.New crawler, SubReplyParser parser, SubReplySaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks.New> locks, Fid fid, Tid tid, Pid pid
        ) : base(logger, dbContextFactory, crawler(tid, pid), parser, saver.Invoke, users, requesterTcs, (locks["subReply"]("subReply"), pid), fid)
        {
            _tid = tid;
            _pid = pid;
        }

        protected override void PostParseCallback((SubReplyResponse, CrawlRequestFlag) responseAndFlag, IList<SubReply> posts) =>
            ParsedPosts.Values.ForEach(p =>
            {
                p.Tid = _tid;
                p.Pid = _pid;
            });
    }
}
