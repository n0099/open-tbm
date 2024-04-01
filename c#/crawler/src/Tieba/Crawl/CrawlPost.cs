namespace tbm.Crawler.Tieba.Crawl;

#pragma warning disable IDE0065 // Misplaced using directive
#pragma warning disable SA1135 // Using directives should be qualified
#pragma warning disable SA1200 // Using directives should be placed correctly
using SavedRepliesKeyByTid = ConcurrentDictionary<Tid, SaverChangeSet<ReplyPost>>;
using SavedThreadsList = IReadOnlyCollection<SaverChangeSet<ThreadPost>>;

public class CrawlPost(
    Func<Owned<CrawlerDbContext.New>> dbContextFactory,
    Func<Owned<ThreadLateCrawlFacade.New>> threadLateCrawlFacadeFactory,
    Func<Owned<ThreadCrawlFacade.New>> threadCrawlFacadeFactory,
    Func<Owned<ReplyCrawlFacade.New>> replyCrawlFacadeFactory,
    Func<Owned<SubReplyCrawlFacade.New>> subReplyCrawlFacadeFactory)
{
    // store the max latestReplyPostedAt of threads appeared in the previous crawl worker, key by fid
    private readonly Dictionary<Fid, Time> _latestReplyPostedAtCheckpointCache = [];

    public async Task<SavedThreadsList> CrawlThreads
        (string forumName, Fid fid, CancellationToken stoppingToken = default)
    {
        stoppingToken.ThrowIfCancellationRequested();
        var savedThreads = new List<SaverChangeSet<ThreadPost>>();
        Time minLatestReplyPostedAt;
        Page crawlingPage = 0;

        if (!_latestReplyPostedAtCheckpointCache.TryGetValue(fid, out var maxLatestReplyPostedAtOccurInPreviousCrawl))
        { // get the largest value of field latestReplyPostedAt in all stored threads of this forum
            // this approach is not as accurate as extracting the last thread in the response list
            // and needs a full table scan on db
            // https://stackoverflow.com/questions/341264/max-or-default
            await using var dbFactory = dbContextFactory();
            maxLatestReplyPostedAtOccurInPreviousCrawl = dbFactory.Value(fid)
                .Threads.Max(th => (Time?)th.LatestReplyPostedAt) ?? Time.MaxValue;
        }
        do
        {
            crawlingPage++;
            await using var facadeFactory = threadCrawlFacadeFactory();
            var facade = facadeFactory.Value(fid, forumName);
            var currentPageChangeSet = (await facade.CrawlPageRange(
                crawlingPage, crawlingPage, stoppingToken)).SaveCrawled(stoppingToken);
            if (currentPageChangeSet != null)
            {
                savedThreads.Add(currentPageChangeSet);
                var threadsLatestReplyPostedAt = currentPageChangeSet.AllAfter
                    .Select(th => th.LatestReplyPostedAt).ToList();
                minLatestReplyPostedAt = threadsLatestReplyPostedAt.Min();
                if (crawlingPage == 1)
                    _latestReplyPostedAtCheckpointCache[fid] = threadsLatestReplyPostedAt.Max();
            }
            else
            { // retry this page
                crawlingPage--;
                minLatestReplyPostedAt = Time.MaxValue;
            }
        } while (minLatestReplyPostedAt > maxLatestReplyPostedAtOccurInPreviousCrawl);

        await Task.WhenAll(savedThreads.Select(async threads =>
        {
            if (stoppingToken.IsCancellationRequested) return;
            var failureCountsKeyByTid = threads.NewlyAdded
                .ToDictionary(th => th.Tid, _ => (FailureCount)0);
            await using var threadLateFacade = threadLateCrawlFacadeFactory();
            await threadLateFacade.Value(fid).CrawlThenSave(failureCountsKeyByTid, stoppingToken);
        }));

        return savedThreads;
    }

    public async Task<SavedRepliesKeyByTid> CrawlReplies
        (SavedThreadsList savedThreads, Fid fid, CancellationToken stoppingToken = default)
    {
        stoppingToken.ThrowIfCancellationRequested();
        var shouldCrawlParentPosts = savedThreads
            .Aggregate(new HashSet<Tid>(), (shouldCrawl, threads) =>
            {
                shouldCrawl.UnionWith(threads.NewlyAdded.Select(th => th.Tid));
                shouldCrawl.UnionWith(threads.Existing.Where(t =>
                {
                    var (before, after) = t;
                    return before.ReplyCount != after.ReplyCount
                           || before.LatestReplyPostedAt != after.LatestReplyPostedAt
                           || before.LatestReplierUid != after.LatestReplierUid;
                }).Select(t => t.Before.Tid));
                return shouldCrawl;
            });
        var savedRepliesKeyByTid = new SavedRepliesKeyByTid();
        await Task.WhenAll(shouldCrawlParentPosts.Select(async tid =>
        {
            if (stoppingToken.IsCancellationRequested) return;
            await using var facadeFactory = replyCrawlFacadeFactory();
            var facade = facadeFactory.Value(fid, tid).AddExceptionHandler(
                SaveThreadMissingFirstReply(fid, tid, savedThreads).Invoke);
            savedRepliesKeyByTid.SetIfNotNull(tid,
                (await facade.CrawlPageRange(1, stoppingToken: stoppingToken)).SaveCrawled(stoppingToken));
        }));
        return savedRepliesKeyByTid;
    }

    public async Task CrawlSubReplies(
        IReadOnlyDictionary<Tid, SaverChangeSet<ReplyPost>> savedRepliesKeyByTid,
        Fid fid,
        CancellationToken stoppingToken = default)
    {
        stoppingToken.ThrowIfCancellationRequested();
        var shouldCrawlParentPosts = savedRepliesKeyByTid
            .Aggregate(new HashSet<(Tid, Pid)>(), (shouldCrawl, pair) =>
            {
                var (tid, replies) = pair;
                shouldCrawl.UnionWith(replies.NewlyAdded
                    .Where(r => r.SubReplyCount != null).Select(r => (tid, r.Pid)));
                shouldCrawl.UnionWith(replies.Existing.Where(t =>
                {
                    var (before, after) = t;
                    return after.SubReplyCount != null && before.SubReplyCount != after.SubReplyCount;
                }).Select(t => (tid, t.Before.Pid)));
                return shouldCrawl;
            });
        await Task.WhenAll(shouldCrawlParentPosts.Select(async t =>
        {
            if (stoppingToken.IsCancellationRequested) return;
            var (tid, pid) = t;
            await using var facadeFactory = subReplyCrawlFacadeFactory();
            var facade = facadeFactory.Value(fid, tid, pid);
            _ = (await facade.CrawlPageRange(1, stoppingToken: stoppingToken))
                .SaveCrawled(stoppingToken);
        }));
    }

    private Action<Exception> SaveThreadMissingFirstReply

        // ReSharper disable once SuggestBaseTypeForParameter
        (Fid fid, Tid tid, SavedThreadsList savedThreads) => ex =>
    {
        if (ex is not EmptyPostListException) return;
        var parentThread = savedThreads
            .SelectMany(c => c.AllAfter.Where(th => th.Tid == tid))
            .FirstOrDefault();
        if (parentThread == null) return;

        var newEntity = new ThreadMissingFirstReply
        {
            Tid = tid,
            Pid = parentThread.FirstReplyPid,
            Excerpt = Helper.SerializedProtoBufWrapperOrNullIfEmpty(parentThread.FirstReplyExcerpt,
                () => new ThreadAbstractWrapper {Value = {parentThread.FirstReplyExcerpt}}),
            DiscoveredAt = Helper.GetNowTimestamp()
        };
        if (newEntity.Pid == null && newEntity.Excerpt == null) return; // skip if all fields are empty

        using var dbFactory = dbContextFactory();
        var db = dbFactory.Value(fid);
        using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);
        var firstReply =
            from r in db.Replies.AsNoTracking()
            where r.Pid == parentThread.FirstReplyPid
            select r.Pid;
        if (firstReply.Any()) return; // skip if the first reply of parent thread had already saved

        var existingEntity = db.ThreadMissingFirstReplies.AsTracking().ForUpdate()
            .SingleOrDefault(e => e.Tid == tid);
        if (existingEntity == null)
        {
            _ = db.ThreadMissingFirstReplies.Add(newEntity);
        }
        else
        {
            if (newEntity.Pid != null) existingEntity.Pid = newEntity.Pid;
            if (newEntity.Excerpt != null) existingEntity.Excerpt = newEntity.Excerpt;
            existingEntity.DiscoveredAt = newEntity.DiscoveredAt;
        }

        _ = db.SaveChanges();
        transaction.Commit();
    };
}
