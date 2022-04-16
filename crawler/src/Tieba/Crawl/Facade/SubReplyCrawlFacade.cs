using Autofac.Features.Indexed;
using Microsoft.Extensions.Logging;
using TbClient.Api.Response;
using TbClient.Post;
using Fid = System.UInt32;
using Tid = System.UInt64;
using Pid = System.UInt64;

namespace tbm.Crawler
{
    public class SubReplyCrawlFacade : BaseCrawlFacade<SubReplyPost, SubReplyResponse, SubReply, SubReplyCrawler>
    {
        private readonly Tid _tid;
        private readonly Pid _pid;

        public delegate SubReplyCrawlFacade New(Fid fid, Tid tid, Pid pid);

        public SubReplyCrawlFacade(ILogger<SubReplyCrawlFacade> logger, SubReplyCrawler.New crawler,
            SubReplyParser parser, SubReplySaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks.New> locks, Fid fid, Tid tid, Pid pid
        ) : base(logger, crawler(tid, pid), parser, saver, users, requesterTcs, (locks["subReply"]("subReply"), fid), fid)
        {
            _tid = tid;
            _pid = pid;
        }

        protected override void ValidateThenParse((SubReplyResponse, CrawlRequestFlag) responseAndFlag)
        {
            Parser.ParsePosts(responseAndFlag.Item2, Crawler.GetValidPosts(responseAndFlag.Item1), Posts, Users);
            Posts.Values.ForEach(p =>
            {
                p.Tid = _tid;
                p.Pid = _pid;
            });
        }
    }
}
