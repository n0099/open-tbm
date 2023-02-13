using Humanizer;
using Humanizer.Localisation;

namespace tbm.Crawler.Worker;

using SavedRepliesKeyByTid = ConcurrentDictionary<ulong, SaverChangeSet<ReplyPost>>;

public class ArchiveCrawlWorker : BackgroundService
{
    // as of March 2019, tieba had restrict the max accepted value for page param of forum's threads api
    // any request with page offset that larger than 10k threads will be respond with results from the first page
    private const int MaxCrawlablePage = 334; // 10k threads / 30 per request (from Rn param) = 333.3...
    private readonly ILogger<ArchiveCrawlWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private readonly string _forumName = "";
    private readonly Fid _fid = 0;

    public ArchiveCrawlWorker(ILogger<ArchiveCrawlWorker> logger, ILifetimeScope scope0)
    {
        _logger = logger;
        _scope0 = scope0;
    }

    public static float CalcCumulativeAverage(float currentCa, float previousCa, int currentIndex) =>
        (currentCa + ((currentIndex - 1) * previousCa)) / currentIndex; // https://en.wikipedia.org/wiki/Moving_average#Cumulative_average

    public static (string Relative, string At) CalcEta(int total, int completed, float averageDurationInMs)
    {
        var etaTimeSpan = TimeSpan.FromMilliseconds((total - completed) * averageDurationInMs);
        return (etaTimeSpan.Humanize(precision: 5, minUnit: TimeUnit.Second), DateTime.Now.Add(etaTimeSpan).ToString("MM-dd HH:mm:ss"));
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        try
        {
            var averageElapsed = 0f; // in seconds
            var finishedPageCount = 0;
            var totalSavedThreadCount = 0;
            var totalSavedReplyCount = 0;
            var totalSavedSubReplyCount = 0;
            var stopWatchPageInterval = new Stopwatch();
            stopWatchPageInterval.Start();

            var totalPage = Math.Min(MaxCrawlablePage, await GetTotalPageForForum(stoppingToken));
            foreach (var pages in Enumerable.Range(1, totalPage).Chunk(Environment.ProcessorCount))
            {
                await Parallel.ForEachAsync(pages, stoppingToken, async (page, cancellationToken) =>
                {
                    var stopWatchTotal = new Stopwatch();
                    stopWatchTotal.Start();
                    var stopWatch = new Stopwatch();
                    stopWatch.Start();
                    string GetHumanizedElapsedTimeThenRestart()
                    {
                        var ret = stopWatch.Elapsed.Humanize(minUnit: TimeUnit.Second);
                        stopWatch.Restart();
                        return ret;
                    }

                    if (cancellationToken.IsCancellationRequested) return;
                    var savedThreads = await CrawlThreads((Page)page, _forumName, _fid, cancellationToken);
                    if (savedThreads == null) return;
                    var savedThreadCount = savedThreads.AllAfter.Count;
                    _logger.LogInformation("Archive for {} threads in the page {} of forum {} finished after {:F2}s",
                        savedThreadCount, page, _forumName, GetHumanizedElapsedTimeThenRestart());
                    _ = Interlocked.Add(ref totalSavedThreadCount, savedThreadCount);

                    if (cancellationToken.IsCancellationRequested) return;
                    var savedReplies = await CrawlReplies(savedThreads, _fid, cancellationToken);
                    var savedReplyCount = savedReplies.Select(i => i.Value.AllAfter.Count).Sum();
                    _logger.LogInformation("Archive for {} replies within {} threads in the page {} of forum {} finished after {:F2}s",
                        savedReplyCount, savedThreadCount, page, _forumName, GetHumanizedElapsedTimeThenRestart());
                    _ = Interlocked.Add(ref totalSavedReplyCount, savedReplyCount);

                    if (cancellationToken.IsCancellationRequested) return;
                    var savedSubReplyCount = await CrawlSubReplies(savedReplies, _fid, cancellationToken);
                    _logger.LogInformation("Archive for {} sub replies within {} replies within {} threads in the page {} of forum {} finished after {:F2}s",
                        savedSubReplyCount, savedReplyCount, savedThreadCount, page, _forumName, GetHumanizedElapsedTimeThenRestart());
                    _logger.LogInformation("Archive for a total of {} posts in the page {} of forum {} finished after {:F2}s",
                        savedSubReplyCount + savedReplyCount + savedThreadCount, page, _forumName, stopWatchTotal.Elapsed.TotalSeconds);
                    _ = Interlocked.Add(ref totalSavedSubReplyCount, savedSubReplyCount);

                    var intervalBetweenPage = (float)stopWatchPageInterval.Elapsed.TotalSeconds;
                    stopWatchPageInterval.Restart();
                    _ = Interlocked.CompareExchange(ref averageElapsed, intervalBetweenPage, 0); // first run
                    _ = Interlocked.Increment(ref finishedPageCount);
                    var ca = CalcCumulativeAverage(intervalBetweenPage, averageElapsed, finishedPageCount); // in seconds
                    _ = Interlocked.Exchange(ref averageElapsed, ca);

                    var (etaRelative, etaAt) = CalcEta(totalPage, finishedPageCount, ca * 1000);
                    _logger.LogInformation("Archive pages progress={}/{} totalSavedPosts={} ({} threads, {} replies, {} subReplies) lastIntervalBetweenPage={:F2}s cumulativeAvgInterval={:F2}s ETA: {} @ {}",
                        finishedPageCount, totalPage,
                        totalSavedThreadCount + totalSavedReplyCount + totalSavedSubReplyCount,
                        totalSavedThreadCount, totalSavedReplyCount, totalSavedSubReplyCount,
                        intervalBetweenPage, ca, etaRelative, etaAt);
                    Console.Title = $"Archive progress: {finishedPageCount}/{totalPage} ETA: {etaRelative} @ {etaAt}";
                });
            }
            _logger.LogInformation("Archive for {} posts({} threads, {} replies, {} subReplies) within all pages [1-{}] of forum {} finished",
                totalSavedThreadCount + totalSavedReplyCount + totalSavedSubReplyCount,
                totalSavedThreadCount, totalSavedReplyCount, totalSavedSubReplyCount, totalPage, _forumName);
        }
        catch (Exception e)
        {
            _logger.LogError(e, "Exception");
        }
    }

    private async Task<int> GetTotalPageForForum(CancellationToken stoppingToken = default)
    {
        await using var scope1 = _scope0.BeginLifetimeScope();
        return (await scope1.Resolve<ThreadArchiveCrawler.New>()(_forumName).CrawlSinglePage(1, stoppingToken))
            .Select(response => response.Result.Data.Page.TotalPage).Max();
    }

    private async Task<SaverChangeSet<ThreadPost>?> CrawlThreads(Page page, string forumName, Fid fid, CancellationToken stoppingToken = default)
    {
        await using var scope1 = _scope0.BeginLifetimeScope();
        var crawler = scope1.Resolve<ThreadArchiveCrawlFacade.New>()(fid, forumName);
        var savedThreads = (await crawler.CrawlPageRange(
            page, page, stoppingToken)).SaveCrawled(stoppingToken);
        if (savedThreads != null)
        {
            await scope1.Resolve<ThreadLateCrawlerAndSaver.New>()(fid)
                .Crawl(savedThreads.NewlyAdded.ToDictionary(t => t.Tid, _ => (FailureCount)0));
        }
        return savedThreads;
    }

    private async Task<SavedRepliesKeyByTid> CrawlReplies(SaverChangeSet<ThreadPost>? savedThreads, Fid fid, CancellationToken stoppingToken = default)
    {
        var savedRepliesKeyByTid = new SavedRepliesKeyByTid();
        if (savedThreads == null) return savedRepliesKeyByTid;
        // some rare thread will have replyNum=0, but contains reply and can be revealed by requesting
        // we choose TO crawl these rare thread's replies for archive since most thread will have replies
        // following sql can figure out existing replies that not matched with parent thread's subReplyNum in db:
        // SELECT COUNT(*) FROM tbmc_f{fid}_thread AS T INNER JOIN tbmc_f{fid}_reply AS R ON T.tid = R.tid AND T.replyNum IS NULL
        await Task.WhenAll(savedThreads.AllAfter.Select(t => t.Tid).Distinct().Select(async tid =>
        {
            if (stoppingToken.IsCancellationRequested) return;
            await using var scope1 = _scope0.BeginLifetimeScope();
            var crawler = scope1.Resolve<ReplyCrawlFacade.New>()(fid, tid);
            savedRepliesKeyByTid.SetIfNotNull(tid,
                (await crawler.CrawlPageRange(1, stoppingToken: stoppingToken)).SaveCrawled(stoppingToken));
        }));
        return savedRepliesKeyByTid;
    }

    private async Task<int> CrawlSubReplies(SavedRepliesKeyByTid savedRepliesKeyByTid, Fid fid, CancellationToken stoppingToken = default)
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
        await Task.WhenAll(shouldCrawlParentPosts.Select(async tuple =>
        {
            if (stoppingToken.IsCancellationRequested) return;
            var (tid, pid) = tuple;
            await using var scope1 = _scope0.BeginLifetimeScope();
            var saved = (await scope1.Resolve<SubReplyCrawlFacade.New>()(fid, tid, pid)
                .CrawlPageRange(1, stoppingToken: stoppingToken)).SaveCrawled(stoppingToken);
            if (saved == null) return;
            _ = Interlocked.Add(ref savedSubReplyCount, saved.AllAfter.Count);
        }));
        return savedSubReplyCount;
    }
}
