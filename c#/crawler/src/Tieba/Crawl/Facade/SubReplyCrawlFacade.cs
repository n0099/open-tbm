namespace tbm.Crawler.Tieba.Crawl.Facade;

public class SubReplyCrawlFacade(
    SubReplyCrawler.New crawlerFactory,
    Fid fid,
    Tid tid,
    Pid pid,
    IIndex<CrawlerLocks.Type, CrawlerLocks> locks,
    SubReplyParser postParser,
    SubReplySaver.New postSaverFactory,
    UserParser.New userParserFactory,
    UserSaver.New userSaverFactory,
    SonicPusher sonicPusher)
    : CrawlFacade<SubReplyPost, SubReplyPost.Parsed, SubReplyResponse, SubReply>(
        crawlerFactory(tid, pid), fid, new(fid, tid, pid), locks[CrawlerLocks.Type.SubReply],
        postParser, postSaverFactory.Invoke,
        userParserFactory.Invoke, userSaverFactory.Invoke)
{
    public delegate SubReplyCrawlFacade New(Fid fid, Tid tid, Pid pid);

    protected override void ThrowIfEmptyUsersEmbedInPosts() => throw new TiebaException(
        $"User list in the response of sub reply request for fid {Fid}, tid {tid}, pid {pid} is empty.");

    protected override void OnPostParse(
        SubReplyResponse response,
        CrawlRequestFlag flag,
        IReadOnlyDictionary<PostId, SubReplyPost.Parsed> parsedPosts)
    {
        foreach (var sr in parsedPosts.Values)
        {
            sr.Tid = tid;
            sr.Pid = pid;
        }
        UserParser.ResetUsersIcon();
    }

    protected override void OnBeforeCommitSave(CrawlerDbContext db, UserSaver userSaver) =>
        userSaver.SaveParentThreadLatestReplierUid(db, tid);

    protected override void OnPostCommitSave(
        SaverChangeSet<SubReplyPost, SubReplyPost.Parsed> savedPosts,
        CancellationToken stoppingToken = default) =>
        sonicPusher.PushPostWithCancellationToken(savedPosts.NewlyAdded, Fid, "subReplies",
            p => p.Spid, p => p.ContentsProtoBuf, stoppingToken);
}
