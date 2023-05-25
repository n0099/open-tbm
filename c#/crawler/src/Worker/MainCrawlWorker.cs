using System.Runtime.CompilerServices;

namespace tbm.Crawler.Worker;

using SavedThreadsList = List<SaverChangeSet<ThreadPost>>;
using SavedRepliesKeyByTid = ConcurrentDictionary<Tid, SaverChangeSet<ReplyPost>>;

public class MainCrawlWorker : CyclicCrawlWorker
{
    private readonly ILifetimeScope _scope0;
    // store the max latestReplyPostedAt of threads appeared in the previous crawl worker, key by fid
    private readonly Dictionary<Fid, Time> _latestReplyPostedAtCheckpointCache = new();

    public MainCrawlWorker(
        ILogger<MainCrawlWorker> logger, IConfiguration config,
        ILifetimeScope scope0, IIndex<string, CrawlerLocks> locks
    ) : base(logger, config)
    {
        _scope0 = scope0;
        // eager initial all keyed CrawlerLocks singleton instances, in order to sync their timer of WithLogTrace
        _ = locks["thread"];
        _ = locks["threadLate"];
        _ = locks["reply"];
        _ = locks["subReply"];
    }

    private record FidAndName(Fid Fid, string Name);

    private async IAsyncEnumerable<FidAndName> ForumGenerator
        ([EnumeratorCancellation] CancellationToken stoppingToken = default)
    {
        await using var scope1 = _scope0.BeginLifetimeScope();
        var db = scope1.Resolve<CrawlerDbContext.New>()(0);
        var forums = (from f in db.Forum.AsNoTracking()
            where f.IsCrawling select new FidAndName(f.Fid, f.Name)).ToList();
        var yieldInterval = SyncCrawlIntervalWithConfig() / (float)forums.Count;
        foreach (var fidAndName in forums)
        {
            yield return fidAndName;
            await Task.Delay((yieldInterval * 1000).RoundToUshort(), stoppingToken);
        }
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await foreach (var (fid, forumName) in ForumGenerator(stoppingToken))
            await CrawlSubReplies(await CrawlReplies(await CrawlThreads(forumName, fid, stoppingToken), fid, stoppingToken), fid, stoppingToken);
    }

    private async Task<SavedThreadsList> CrawlThreads(string forumName, Fid fid, CancellationToken stoppingToken = default)
    {
        stoppingToken.ThrowIfCancellationRequested();
        var savedThreads = new SavedThreadsList();
        Time minLatestReplyPostedAt;
        Page crawlingPage = 0;
        await using var scope1 = _scope0.BeginLifetimeScope();
        if (!_latestReplyPostedAtCheckpointCache.TryGetValue(fid, out var maxLatestReplyPostedAtOccurInPreviousCrawl))
            // get the largest value of field latestReplyPostedAt in all stored threads of this forum
            // this approach is not as accurate as extracting the last thread in the response list and needs a full table scan on db
            // https://stackoverflow.com/questions/341264/max-or-default
            maxLatestReplyPostedAtOccurInPreviousCrawl =
                scope1.Resolve<CrawlerDbContext.New>()(fid).Threads
                    .Max(th => (Time?)th.LatestReplyPostedAt) ?? Time.MaxValue;
        do
        {
            crawlingPage++;
            await using var scope2 = scope1.BeginLifetimeScope();
            var crawler = scope2.Resolve<ThreadCrawlFacade.New>()(fid, forumName);
            var currentPageChangeSet = (await crawler.CrawlPageRange(
                crawlingPage, crawlingPage, stoppingToken)).SaveCrawled(stoppingToken);
            if (currentPageChangeSet != null)
            {
                savedThreads.Add(currentPageChangeSet);
                var threadsLatestReplyPostedAt = currentPageChangeSet.AllAfter
                    .Select(th => th.LatestReplyPostedAt).ToList();
                minLatestReplyPostedAt = threadsLatestReplyPostedAt.Min();
                if (crawlingPage == 1) _latestReplyPostedAtCheckpointCache[fid] = threadsLatestReplyPostedAt.Max();
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
            await using var scope3 = _scope0.BeginLifetimeScope();
            var failureCountsKeyByTid = threads.NewlyAdded.ToDictionary(th => th.Tid, _ => (FailureCount)0);
            await scope3.Resolve<ThreadLateCrawlerAndSaver.New>()(fid).Crawl(failureCountsKeyByTid, stoppingToken);
        }));

        return savedThreads;
    }

    private async Task<SavedRepliesKeyByTid> CrawlReplies
        (SavedThreadsList savedThreads, Fid fid, CancellationToken stoppingToken = default) =>
        await CrawlReplies(savedThreads, fid, _scope0, stoppingToken);

    public static async Task<SavedRepliesKeyByTid> CrawlReplies(
        SavedThreadsList savedThreads, Fid fid,
        ILifetimeScope scope, CancellationToken stoppingToken = default)
    {
        stoppingToken.ThrowIfCancellationRequested();
        var shouldCrawlParentPosts = savedThreads.Aggregate(new HashSet<Tid>(), (shouldCrawl, threads) =>
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
            await using var scope1 = scope.BeginLifetimeScope();
            var crawler = scope1.Resolve<ReplyCrawlFacade.New>()(fid, tid)
                .AddExceptionHandler(SaveThreadMissingFirstReply(scope1, fid, tid, savedThreads).Invoke);
            savedRepliesKeyByTid.SetIfNotNull(tid,
                (await crawler.CrawlPageRange(1, stoppingToken: stoppingToken)).SaveCrawled(stoppingToken));
        }));
        return savedRepliesKeyByTid;
    }

    private static Action<Exception> SaveThreadMissingFirstReply(ILifetimeScope scope, Fid fid, Tid tid, SavedThreadsList savedThreads) => ex =>
    {
        if (ex is not EmptyPostListException) return;
        var parentThread = savedThreads.SelectMany(c => c.AllAfter.Where(th => th.Tid == tid)).FirstOrDefault();
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

        var db = scope.Resolve<CrawlerDbContext.New>()(fid);
        using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);
        var firstReply = from r in db.Replies.AsNoTracking()
            where r.Pid == parentThread.FirstReplyPid select r.Pid;
        if (firstReply.Any()) return; // skip if the first reply of parent thread had already saved

        var existingEntity = db.ThreadMissingFirstReplies.AsTracking().TagWith("ForUpdate")
            .SingleOrDefault(e => e.Tid == tid);
        if (existingEntity == null) _ = db.ThreadMissingFirstReplies.Add(newEntity);
        else
        {
            if (newEntity.Pid != null) existingEntity.Pid = newEntity.Pid;
            if (newEntity.Excerpt != null) existingEntity.Excerpt = newEntity.Excerpt;
            existingEntity.DiscoveredAt = newEntity.DiscoveredAt;
        }

        _ = db.SaveChanges();
        transaction.Commit();
    };

    private async Task CrawlSubReplies
        (SavedRepliesKeyByTid savedRepliesKeyByTid, Fid fid, CancellationToken stoppingToken = default) =>
        await CrawlSubReplies(savedRepliesKeyByTid, fid, _scope0, stoppingToken);

    public static async Task CrawlSubReplies(
        IDictionary<Tid, SaverChangeSet<ReplyPost>> savedRepliesKeyByTid, Fid fid,
        ILifetimeScope scope, CancellationToken stoppingToken = default)
    {
        stoppingToken.ThrowIfCancellationRequested();
        var shouldCrawlParentPosts = savedRepliesKeyByTid.Aggregate(new HashSet<(Tid, Pid)>(), (shouldCrawl, pair) =>
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
            await using var scope1 = scope.BeginLifetimeScope();
            var crawler = scope1.Resolve<SubReplyCrawlFacade.New>()(fid, tid, pid);
            _ = (await crawler.CrawlPageRange(1, stoppingToken: stoppingToken)).SaveCrawled(stoppingToken);
        }));
    }
}
