namespace tbm.Crawler.Tieba.Crawl.Facade;

public class ReplyCrawlFacade(
        CrawlerDbContext.New dbContextFactory,
        ReplyCrawler.New crawler,
        ReplyParser parser,
        ReplySaver.New saver,
        SonicPusher pusher,
        IIndex<string, CrawlerLocks> locks,
        Fid fid,
        Tid tid)
    : BaseCrawlFacade<ReplyPost, BaseReplyRevision, ReplyResponse, Reply>
        (crawler(fid, tid), parser, saver.Invoke, locks["reply"], new(fid, tid), fid)
{
    public delegate ReplyCrawlFacade New(Fid fid, Tid tid);

    protected override void PostParseHook(
        ReplyResponse response,
        CrawlRequestFlag flag,
        IDictionary<PostId, ReplyPost> parsedPostsInResponse)
    {
        parsedPostsInResponse.Values.ForEach(r => r.Tid = tid);
        var data = response.Data;
        Users.ParseUsers(data.UserList);
        FillAuthorInfoBackToReply(data.UserList, parsedPostsInResponse.Values);
        if (data.Page.CurrentPage == 1) SaveParentThreadTitle(data.PostList);
    }

    protected override void PostCommitSaveHook(
        SaverChangeSet<ReplyPost> savedPosts,
        CancellationToken stoppingToken = default) =>
        pusher.PushPostWithCancellationToken(savedPosts.NewlyAdded, Fid, "replies",
            p => p.Pid, p => p.OriginalContents, stoppingToken);

    // fill the values for some field of reply from user list which is out of post list
    private static void FillAuthorInfoBackToReply(IEnumerable<TbClient.User> users, IEnumerable<ReplyPost> parsedReplies) =>
        (from reply in parsedReplies
            join user in users on reply.AuthorUid equals user.Uid
            select (reply, user))
        .ForEach(t => t.reply.AuthorExpGrade = (byte)t.user.LevelId);

    private void SaveParentThreadTitle(IEnumerable<Reply> replies)
    {
        // update the parent thread of reply with the new title extracted from the first-floor reply in the first page
        var db = dbContextFactory(Fid);
        using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);

        var parentThreadTitle = (
            from t in db.Threads.AsNoTracking().ForUpdate()
            where t.Tid == tid
            select t.Title).SingleOrDefault();

        // thread title will be empty string as a fallback when the thread author haven't written title for this thread
        if (parentThreadTitle != "") return;
        var newTitle = replies.FirstOrDefault(r => r.Floor == 1)?.Title;
        if (newTitle == null) return;

        db.Attach(new ThreadPost {Tid = tid, Title = newTitle})
            .Property(th => th.Title).IsModified = true;
        if (db.SaveChanges() != 1) // do not touch UpdateAt field for the accuracy of time field in thread revisions
            throw new DbUpdateException(
                $"Parent thread title \"{newTitle}\" completion for tid {tid} has failed.");
        transaction.Commit();
    }
}
