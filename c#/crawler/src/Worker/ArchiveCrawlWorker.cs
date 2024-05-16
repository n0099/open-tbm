namespace tbm.Crawler.Worker;

#pragma warning disable IDE0065 // Misplaced using directive
#pragma warning disable SA1135 // Using directives should be qualified
#pragma warning disable SA1200 // Using directives should be placed correctly
using SavedRepliesKeyByTid = ConcurrentDictionary<Tid, SaverChangeSet<ReplyPost>>;

public class ArchiveCrawlWorker(
    ILogger<ArchiveCrawlWorker> logger,
    Func<Owned<ThreadLateCrawlFacade.New>> threadLateCrawlFacadeFactory,
    Func<Owned<ThreadArchiveCrawler.New>> threadArchiveCrawlerFactory,
    Func<Owned<ThreadArchiveCrawlFacade.New>> threadArchiveCrawlFacadeFactory,
    Func<Owned<ReplyCrawlFacade.New>> replyCrawlFacadeFactory,
    Func<Owned<SubReplyCrawlFacade.New>> subReplyCrawlFacadeFactory)
    : ErrorableWorker(shouldExitOnException: true, shouldExitOnFinish: true)
{
    // as of March 2019, tieba had restrict the max accepted value for page param of forum's threads api
    // any request with page offset that larger than 10k threads will be responded with results from the first page
    // in May 2023, they enlarged the limit to 100k threads: https://github.com/Starry-OvO/aiotieba/issues/124
    private const int MaxCrawlablePage = 3334; // 100k threads / 30 per request (from Rn param) = 3333.3...

    // ReSharper disable once ConvertToConstant.Local
    private readonly string _forumName = "";

    // ReSharper disable once ConvertToConstant.Local
    private readonly Fid _fid = 1;

    // https://en.wikipedia.org/wiki/Moving_average#Cumulative_average
    public static float GetCumulativeAverage(float currentCa, float previousCa, int currentIndex) =>
        (currentCa + ((currentIndex - 1) * previousCa)) / currentIndex;

    [SuppressMessage("Correctness", "SS002:DateTime.Now was referenced")]
    public static (string Relative, string At) GetEta(int total, int completed, float averageDurationInMs)
    {
        var etaTimeSpan = TimeSpan.FromMilliseconds((total - completed) * averageDurationInMs);
        return (etaTimeSpan.Humanize(precision: 5, minUnit: TimeUnit.Second),
            DateTime.Now.Add(etaTimeSpan).ToString("MM-dd HH:mm:ss", CultureInfo.CurrentCulture));
    }

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        var averageElapsed = 0f; // in seconds
        var finishedPageCount = 0;
        var totalSavedThreadCount = 0;
        var totalSavedReplyCount = 0;
        var totalSavedSubReplyCount = 0;
        var stopwatchPageInterval = new Stopwatch();
        stopwatchPageInterval.Start();
        var totalPage = Math.Min(MaxCrawlablePage, await GetTotalPageForForum(stoppingToken));

        async ValueTask ArchivePostsInPage(int page, CancellationToken cancellationToken)
        {
            var stopwatchTotal = new Stopwatch();
            stopwatchTotal.Start();
            var stopwatch = new Stopwatch();
            stopwatch.Start();
            string GetHumanizedElapsedTimeThenRestart()
            {
                var ret = stopwatch.Elapsed.Humanize(minUnit: TimeUnit.Second);
                stopwatch.Restart();
                return ret;
            }

            if (cancellationToken.IsCancellationRequested) return;
            var savedThreads = await CrawlThreads((Page)page, _forumName, _fid, cancellationToken);
            if (savedThreads == null) return;
            var savedThreadCount = savedThreads.AllAfter.Count;
            logger.LogInformation("Archive for {} threads in the page {} of forum {} finished after {:F2}s",
                savedThreadCount, page, _forumName, GetHumanizedElapsedTimeThenRestart());
            _ = Interlocked.Add(ref totalSavedThreadCount, savedThreadCount);

            if (cancellationToken.IsCancellationRequested) return;
            var savedReplies = await CrawlReplies(savedThreads, _fid, cancellationToken);
            var savedReplyCount = savedReplies.Sum(pair => pair.Value.AllAfter.Count);
            logger.LogInformation("Archive for {} replies within {} threads in the page {} of forum {} finished after {:F2}s",
                savedReplyCount, savedThreadCount, page, _forumName, GetHumanizedElapsedTimeThenRestart());
            _ = Interlocked.Add(ref totalSavedReplyCount, savedReplyCount);

            if (cancellationToken.IsCancellationRequested) return;
            var savedSubReplyCount = await CrawlSubReplies(savedReplies, _fid, cancellationToken);
            logger.LogInformation("Archive for {} sub replies within {} replies within {} threads in the page {} of forum {} finished after {:F2}s",
                savedSubReplyCount, savedReplyCount, savedThreadCount, page, _forumName, GetHumanizedElapsedTimeThenRestart());
            logger.LogInformation("Archive for a total of {} posts in the page {} of forum {} finished after {:F2}s",
                savedSubReplyCount + savedReplyCount + savedThreadCount, page, _forumName, stopwatchTotal.Elapsed.TotalSeconds);
            _ = Interlocked.Add(ref totalSavedSubReplyCount, savedSubReplyCount);

            var intervalBetweenPage = (float)stopwatchPageInterval.Elapsed.TotalSeconds;
            stopwatchPageInterval.Restart();
            _ = Interlocked.CompareExchange(ref averageElapsed, intervalBetweenPage, 0); // first run
            _ = Interlocked.Increment(ref finishedPageCount);
            var ca = GetCumulativeAverage(intervalBetweenPage, averageElapsed, finishedPageCount); // in seconds
            _ = Interlocked.Exchange(ref averageElapsed, ca);

            var (etaRelative, etaAt) = GetEta(totalPage, finishedPageCount, ca * 1000);
            logger.LogInformation("Archive pages progress={}/{} totalSavedPosts={} ({} threads, {} replies, {} subReplies) lastIntervalBetweenPage={:F2}s cumulativeAvgInterval={:F2}s ETA: {} @ {}",
                finishedPageCount, totalPage, totalSavedThreadCount + totalSavedReplyCount + totalSavedSubReplyCount,
                totalSavedThreadCount, totalSavedReplyCount, totalSavedSubReplyCount, intervalBetweenPage, ca, etaRelative, etaAt);
            Console.Title = $"Archive progress: {finishedPageCount}/{totalPage} ETA: {etaRelative} @ {etaAt}";
        }

        foreach (var pages in Enumerable.Range(1, totalPage).Chunk(Environment.ProcessorCount))
        {
            await Parallel.ForEachAsync(pages, stoppingToken, ArchivePostsInPage);
        }
        logger.LogInformation("Archive for {} posts({} threads, {} replies, {} subReplies) within all pages [1-{}] of forum {} finished",
            totalSavedThreadCount + totalSavedReplyCount + totalSavedSubReplyCount,
            totalSavedThreadCount, totalSavedReplyCount, totalSavedSubReplyCount, totalPage, _forumName);
    }

    private async Task<int> GetTotalPageForForum(CancellationToken stoppingToken = default)
    {
        await using var crawlerFactory = threadArchiveCrawlerFactory();
        return (await crawlerFactory.Value(_forumName).CrawlSinglePage(1, stoppingToken))
            .Max(response => response.Result.Data.Page.TotalPage);
    }

    private async Task<SaverChangeSet<ThreadPost>?> CrawlThreads
        (Page page, string forumName, Fid fid, CancellationToken stoppingToken = default)
    {
        await using var facadeFactory = threadArchiveCrawlFacadeFactory();
        var facade = facadeFactory.Value(fid, forumName);
        var savedThreads = (await facade.CrawlPageRange(
            page, page, stoppingToken)).SaveCrawled(stoppingToken);

        // ReSharper disable once InvertIf
        if (savedThreads != null)
        {
            var failureCountsKeyByTid = savedThreads.NewlyAdded
                .ToDictionary(th => th.Tid, _ => (FailureCount)0);
            await using var threadLateFacade = threadLateCrawlFacadeFactory();
            await threadLateFacade.Value(fid).CrawlThenSave(failureCountsKeyByTid, stoppingToken);
        }
        return savedThreads;
    }

    private async Task<SavedRepliesKeyByTid> CrawlReplies
        (SaverChangeSet<ThreadPost>? savedThreads, Fid fid, CancellationToken stoppingToken = default)
    {
        var savedRepliesKeyByTid = new SavedRepliesKeyByTid();
        if (savedThreads == null) return savedRepliesKeyByTid;

        // some rare thread will have replyNum=0, but contains reply and can be revealed by requesting
        // we choose TO crawl these rare thread's replies for archive since most thread will have replies
        // following sql can figure out existing replies that not matched with parent thread's subReplyNum in db:
        // SELECT COUNT(*) FROM tbmc_f{fid}_thread AS T INNER JOIN tbmc_f{fid}_reply AS R ON T.tid = R.tid AND T.replyNum IS NULL
        await Task.WhenAll(savedThreads.AllAfter.Select(th => th.Tid).Distinct().Select(async tid =>
        {
            if (stoppingToken.IsCancellationRequested) return;
            await using var facadeFactory = replyCrawlFacadeFactory();
            var facade = facadeFactory.Value(fid, tid);
            savedRepliesKeyByTid.SetIfNotNull(tid,
                (await facade.CrawlPageRange(1, stoppingToken: stoppingToken)).SaveCrawled(stoppingToken));
        }));
        return savedRepliesKeyByTid;
    }

    private async Task<int> CrawlSubReplies
        (SavedRepliesKeyByTid savedRepliesKeyByTid, Fid fid, CancellationToken stoppingToken = default)
    {
        var shouldCrawlParentPosts = savedRepliesKeyByTid.Aggregate(new HashSet<(Tid, Pid)>(), (shouldCrawl, pair) =>
        {
            var (tid, replies) = pair;

            // some rare reply will have SubReplyCount=0, but contains sub reply and can be revealed by requesting
            // we choose NOT TO crawl these rare reply's sub replies for archive since most reply won't have sub replies
            // following sql can figure out existing sub replies that not matched with parent reply's SubReplyCount in db:
            // SELECT COUNT(*) FROM tbmc_f{fid}_reply AS R INNER JOIN tbmc_f{fid}_subReply AS SR ON R.pid = SR.pid AND R.subReplyCount IS NULL
            shouldCrawl.UnionWith(replies.AllAfter
                .Where(r => r.SubReplyCount != null).Select(r => (tid, r.Pid)));
            return shouldCrawl;
        });
        var savedSubReplyCount = 0;
        await Task.WhenAll(shouldCrawlParentPosts.Select(async t =>
        {
            if (stoppingToken.IsCancellationRequested) return;
            var (tid, pid) = t;
            await using var facadeFactory = subReplyCrawlFacadeFactory();
            var saved = (await facadeFactory.Value(fid, tid, pid)
                .CrawlPageRange(1, stoppingToken: stoppingToken)).SaveCrawled(stoppingToken);
            if (saved == null) return;
            _ = Interlocked.Add(ref savedSubReplyCount, saved.AllAfter.Count);
        }));
        return savedSubReplyCount;
    }
}
