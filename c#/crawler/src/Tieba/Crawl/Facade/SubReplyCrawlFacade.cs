namespace tbm.Crawler.Tieba.Crawl.Facade;

public class SubReplyCrawlFacade : BaseCrawlFacade<SubReplyPost, BaseSubReplyRevision, SubReplyResponse, SubReply, SubReplyCrawler>
{
    private readonly SonicPusher _pusher;
    private readonly Tid _tid;
    private readonly Pid _pid;

    public delegate SubReplyCrawlFacade New(Fid fid, Tid tid, Pid pid);

    public SubReplyCrawlFacade(ILogger<SubReplyCrawlFacade> logger, CrawlerDbContext.New dbContextFactory,
        SubReplyCrawler.New crawler, SubReplyParser parser, SubReplySaver.New saver, UserParserAndSaver users, SonicPusher pusher,
        ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks> locks, Fid fid, Tid tid, Pid pid
    ) : base(logger, dbContextFactory, crawler(tid, pid), parser, saver.Invoke, users, requesterTcs, (locks["subReply"], new (fid, tid, pid)), fid) =>
        (_pusher, _tid, _pid) = (pusher, tid, pid);

    protected override void ThrowIfEmptyUsersEmbedInPosts() =>
        throw new TiebaException(
            $"User list in the response of sub reply request for fid {Fid}, tid {_tid}, pid {_pid} is empty.");

    protected override void PostParseHook(SubReplyResponse response, CrawlRequestFlag flag, Dictionary<PostId, SubReplyPost> parsedPostsInResponse)
    {
        foreach (var sr in parsedPostsInResponse.Values)
        {
            sr.Tid = _tid;
            sr.Pid = _pid;
        }
        Users.ResetUsersIcon();
    }

    protected override void PostCommitSaveHook(SaverChangeSet<SubReplyPost> savedPosts, CancellationToken stoppingToken = default) =>
        _pusher.PushPostWithCancellationToken(savedPosts.NewlyAdded, Fid, "subReplies",
            p => p.Spid, p => p.OriginalContents, stoppingToken);
}
