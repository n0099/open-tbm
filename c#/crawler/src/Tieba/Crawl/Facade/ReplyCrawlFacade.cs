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
    SonicPusher sonicPusher)
    : CrawlFacade<ReplyPost, ReplyResponse, Reply>(
        crawlerFactory(fid, tid), fid, new(fid, tid), locks[CrawlerLocks.Type.Reply],
        postParser, postSaverFactory.Invoke,
        userParserFactory.Invoke, userSaverFactory.Invoke)
{
    private string? _parentThreadTitle;

    public delegate ReplyCrawlFacade New(Fid fid, Tid tid);

    protected override void OnPostParse(
        ReplyResponse response,
        CrawlRequestFlag flag,
        IReadOnlyDictionary<PostId, ReplyPost> parsedPostsInResponse)
    {
        parsedPostsInResponse.Values.ForEach(r => r.Tid = tid);
        var data = response.Data;
        UserParser.Parse(data.UserList);
        FillAuthorInfoBackToReply(data.UserList, parsedPostsInResponse.Values);
        if (data.Page.CurrentPage == 1)
            _parentThreadTitle = data.PostList.FirstOrDefault(r => r.Floor == 1)?.Title;
    }

    protected override void OnBeforeCommitSave(CrawlerDbContext db, UserSaver userSaver)
    {
        if (_parentThreadTitle == null) return;
        var thread = db.Threads.AsTracking().SingleOrDefault(t => t.Tid == tid && t.Title != _parentThreadTitle);
        if (thread == null

            // thread title will be empty string as a fallback when the thread author haven't written title for this thread
            // != null && Title != ""
            || thread is {Title: not ""}) return;

        // update the parent thread of reply with the new title extracted from the first-floor reply in the first page
        // when they are different, in history reply author may custom its title: https://z.n0099.net/#narrow/near/98236
        thread.Title = _parentThreadTitle;
    }

    protected override void OnPostCommitSave(
        SaverChangeSet<ReplyPost> savedPosts,
        CancellationToken stoppingToken = default) =>
        sonicPusher.PushPostWithCancellationToken(savedPosts.NewlyAdded, Fid, "replies",
            p => p.Pid, p => p.ContentsProtoBuf, stoppingToken);

    // fill the values for some field of reply from user list which is out of post list
    private static void FillAuthorInfoBackToReply(IEnumerable<TbClient.User> users, IEnumerable<ReplyPost> parsedReplies) =>
        (from reply in parsedReplies
            join user in users on reply.AuthorUid equals user.Uid
            select (reply, user))
        .ForEach(t => t.reply.AuthorExpGrade = (byte)t.user.LevelId);
}
