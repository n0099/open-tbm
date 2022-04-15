using Autofac.Features.Indexed;
using Microsoft.Extensions.Logging;
using TbClient.Api.Response;
using TbClient.Post;
using Fid = System.UInt32;

namespace tbm.Crawler
{
    public class ThreadCrawlFacade : BaseCrawlFacade<ThreadPost, ThreadResponse, Thread, ThreadCrawler>
    {
        public delegate ThreadCrawlFacade New(Fid fid, string forumName);

        public ThreadCrawlFacade(ILogger<ThreadCrawlFacade> logger, ThreadCrawler.New crawler,
            IParser<ThreadPost, Thread> parser, BaseSaver<ThreadPost> saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks.New> locks, Fid fid, string forumName
            ) : base(logger, crawler(fid, forumName), parser, saver, users, requesterTcs, (locks["thread"]("thread"), fid), fid)
        {
        }
    }
}
