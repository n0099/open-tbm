namespace tbm.Crawler.Tieba.Crawl.Facade;

public class ReplyCrawlFacade : BaseCrawlFacade<ReplyPost, BaseReplyRevision, ReplyResponse, Reply, ReplyCrawler>
{
    private readonly CrawlerDbContext.New _dbContextFactory;
    private readonly SonicPusher _pusher;
    private readonly Tid _tid;

    public delegate ReplyCrawlFacade New(Fid fid, Tid tid);

    public ReplyCrawlFacade(ILogger<ReplyCrawlFacade> logger,
        CrawlerDbContext.New parentDbContextFactory, CrawlerDbContext.New dbContextFactory,
        ReplyCrawler.New crawler, ReplyParser parser, ReplySaver.New saver, UserParserAndSaver users, SonicPusher pusher,
        ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks> locks, Fid fid, Tid tid
    ) : base(logger, parentDbContextFactory, crawler(fid, tid), parser, saver.Invoke, users, requesterTcs, (locks["reply"], new (fid, tid)), fid)
    {
        _dbContextFactory = dbContextFactory;
        _pusher = pusher;
        _tid = tid;
    }

    protected override void PostParseHook(ReplyResponse response, CrawlRequestFlag flag, Dictionary<PostId, ReplyPost> parsedPostsInResponse)
    {
        parsedPostsInResponse.Values.ForEach(r => r.Tid = _tid);
        var data = response.Data;
        Users.ParseUsers(data.UserList);
        FillAuthorInfoBackToReply(data.UserList, parsedPostsInResponse.Values);
        if (data.Page.CurrentPage == 1) SaveParentThreadTitle(data.PostList);
    }

    private static void FillAuthorInfoBackToReply(IEnumerable<User> users, IEnumerable<ReplyPost> parsedReplies) =>
        (from reply in parsedReplies
            join user in users on reply.AuthorUid equals user.Uid
            select (reply, user))
        // fill the values for some field of reply from user list which is out of post list
        .ForEach(t => t.reply.AuthorExpGrade = (ushort)t.user.LevelId);

    private void SaveParentThreadTitle(IEnumerable<Reply> replies)
    {
        // update the parent thread of reply with the new title extracted from the first-floor reply in the first page
        var db = _dbContextFactory(Fid);
        using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);

        var parentThreadTitle = (from t in db.Threads.AsNoTracking().TagWith("ForUpdate")
            where t.Tid == _tid select t.Title).SingleOrDefault();
        // thread title will be empty string as a fallback when the thread author haven't write title for this thread
        if (parentThreadTitle != "") return;
        var newTitle = replies.FirstOrDefault(r => r.Floor == 1)?.Title;
        if (newTitle == null) return;

        db.Attach(new ThreadPost {Tid = _tid, Title = newTitle})
            .Property(th => th.Title).IsModified = true;
        if (db.SaveChanges() != 1) // do not touch UpdateAt field for the accuracy of time field in thread revisions
            throw new DbUpdateException(
                $"Parent thread title \"{newTitle}\" completion for tid {_tid} has failed.");
        transaction.Commit();
    }

    protected override void PostCommitSaveHook(SaverChangeSet<ReplyPost> savedPosts, CancellationToken stoppingToken = default) =>
        _pusher.PushPostWithCancellationToken(savedPosts.NewlyAdded, Fid, "replies",
            p => p.Pid, p => p.OriginalContents, stoppingToken);
}
