namespace tbm.Crawler.Tieba.Crawl.Facade;

public class ReplyCrawlFacade(
        ReplyCrawler.New crawlerFactory,
        Fid fid,
        Tid tid,
        IIndex<CrawlerLocks.Type, CrawlerLocks> locks,
        ReplyParser postParser,
        ReplySaver.New postSaverFactory,
        UserParser.New userParserFactory,
        UserSaver.New userSaverFactory,
        CrawlerDbContext.New dbContextFactory,
        SonicPusher sonicPusher)
    : CrawlFacade<ReplyPost, BaseReplyRevision, ReplyResponse, Reply>(
        crawlerFactory(fid, tid), fid, new(fid, tid), locks[CrawlerLocks.Type.Reply],
        postParser, postSaverFactory.Invoke,
        userParserFactory.Invoke, userSaverFactory.Invoke)
{
    public delegate ReplyCrawlFacade New(Fid fid, Tid tid);

    protected override void PostParseHook(
        ReplyResponse response,
        CrawlRequestFlag flag,
        IReadOnlyDictionary<PostId, ReplyPost> parsedPostsInResponse)
    {
        parsedPostsInResponse.Values.ForEach(r => r.Tid = tid);
        var data = response.Data;
        UserParser.Parse(data.UserList);
        FillAuthorInfoBackToReply(data.UserList, parsedPostsInResponse.Values);
        if (data.Page.CurrentPage == 1) SaveParentThreadTitle(data.PostList);
    }

    protected override void PostCommitSaveHook(
        SaverChangeSet<ReplyPost> savedPosts,
        CancellationToken stoppingToken = default) =>
        sonicPusher.PushPostWithCancellationToken(savedPosts.NewlyAdded, Fid, "replies",
            p => p.Pid, p => p.OriginalContents, stoppingToken);

    // fill the values for some field of reply from user list which is out of post list
    private static void FillAuthorInfoBackToReply(IEnumerable<TbClient.User> users, IEnumerable<ReplyPost> parsedReplies) =>
        (from reply in parsedReplies
            join user in users on reply.AuthorUid equals user.Uid
            select (reply, user))
        .ForEach(t => t.reply.AuthorExpGrade = (byte)t.user.LevelId);

    private void SaveParentThreadTitle(IEnumerable<Reply> replies)
    {
        var newTitle = replies.FirstOrDefault(r => r.Floor == 1)?.Title;
        if (newTitle == null) return;

        // update the parent thread of reply with the new title extracted from the first-floor reply in the first page
        var db = dbContextFactory(Fid);
        using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);

        var thread = db.Threads.AsTracking().SingleOrDefault(t => t.Tid == tid);
        if (thread?.Title == newTitle) return;
        switch (thread)
        { // thread title will be empty string as a fallback when the thread author haven't written title for this thread
            case {Title: not ""}:
                return; // != null && Title != ""
            case null:
                _ = db.Add(new ThreadPost {Tid = tid, Title = newTitle});
                break;
            default:
                thread.Title = newTitle;
                break;
        }

        _ = db.SaveChangesForUpdate();
        transaction.Commit();
    }
}
