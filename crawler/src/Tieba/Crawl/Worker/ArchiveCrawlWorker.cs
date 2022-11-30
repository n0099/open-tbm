using Humanizer;

namespace tbm.Crawler
{
    using SavedRepliesKeyByTid = ConcurrentDictionary<Tid, SaverChangeSet<ReplyPost>>;

    public class ArchiveCrawlWorker : BackgroundService
    {
        // as of March 2019, tieba had restrict the max accepted value for page param of forum's threads api
        // any request with page offset that larger than 10k threads will be response with results from the first page
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

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            try
            {
                var averageElapsed = 0f;
                var finishedPageCount = 0;
                float CalcCumulativeAverage(float current, float previous, int currentCount) =>
                    (current + ((currentCount - 1) * previous)) / currentCount; // https://en.wikipedia.org/wiki/Moving_average#Cumulative_average
                var totalSavedThreadsCount = 0;
                var totalSavedRepliesCount = 0;
                var totalSavedSubRepliesCount = 0;
                var stopWatchPageInterval = new Stopwatch();
                stopWatchPageInterval.Start();

                var totalPage = Math.Min(MaxCrawlablePage, await GetTotalPageForForum());
                foreach (var pages in Enumerable.Range(1, totalPage).Chunk(Environment.ProcessorCount))
                {
                    await Parallel.ForEachAsync(pages, stoppingToken, async (page, cancellationToken) =>
                    {
                        var stopWatchTotal = new Stopwatch();
                        stopWatchTotal.Start();
                        var stopWatch = new Stopwatch();
                        stopWatch.Start();
                        float GetElapsedMsThenRestart()
                        {
                            var ret = stopWatch.ElapsedMilliseconds / 1000f;
                            stopWatch.Restart();
                            return ret;
                        }
                        var savedThreads = await CrawlThreads((Page)page, _forumName, _fid);
                        if (savedThreads == null) return;
                        var savedThreadsCount = savedThreads.AllAfter.Count;
                        _logger.LogInformation("Archive for {} threads in the page {} of forum {} finished after {:F2}s",
                            savedThreadsCount, page, _forumName, GetElapsedMsThenRestart());
                        _ = Interlocked.Add(ref totalSavedThreadsCount, savedThreadsCount);

                        var savedReplies = await CrawlReplies(savedThreads, _fid);
                        var savedRepliesCount = savedReplies.Select(i => i.Value.AllAfter.Count).Sum();
                        _logger.LogInformation("Archive for {} replies within {} threads in the page {} of forum {} finished after {:F2}s",
                            savedRepliesCount, savedThreadsCount, page, _forumName, GetElapsedMsThenRestart());
                        _ = Interlocked.Add(ref totalSavedRepliesCount, savedRepliesCount);

                        var savedSubRepliesCount = await CrawlSubReplies(savedReplies, _fid);
                        _logger.LogInformation("Archive for {} sub replies within {} replies within {} threads in the page {} of forum {} finished after {:F2}s",
                            savedSubRepliesCount, savedRepliesCount, savedThreadsCount, page, _forumName, GetElapsedMsThenRestart());
                        _logger.LogInformation("Archive for a total of {} posts in the page {} of forum {} finished after {:F2}s",
                            savedSubRepliesCount + savedRepliesCount + savedThreadsCount, page, _forumName, stopWatchTotal.ElapsedMilliseconds / 1000f);
                        _ = Interlocked.Add(ref totalSavedSubRepliesCount, savedSubRepliesCount);

                        var intervalBetweenPage = stopWatchPageInterval.ElapsedMilliseconds / 1000f;
                        stopWatchPageInterval.Restart();
                        _ = Interlocked.CompareExchange(ref averageElapsed, intervalBetweenPage, 0); // first run
                        _ = Interlocked.Increment(ref finishedPageCount);
                        var ca = CalcCumulativeAverage(intervalBetweenPage, averageElapsed, finishedPageCount);
                        _ = Interlocked.Exchange(ref averageElapsed, ca);
                        var etaDateTime = DateTime.Now.Add(TimeSpan.FromSeconds((totalPage - finishedPageCount) * ca));
                        var etaRelative = etaDateTime.Humanize();
                        var etaAt = etaDateTime.ToString("MM-dd HH:mm");
                        _logger.LogInformation("Archive pages progress={}/{} totalSavedPosts={}({} threads, {} replies, {} subReplies) lastIntervalBetweenPage={:F2}s avgInterval={:F2}s ETA={} {}",
                            finishedPageCount, totalPage,
                            totalSavedThreadsCount + totalSavedRepliesCount + totalSavedSubRepliesCount,
                            totalSavedThreadsCount, totalSavedRepliesCount, totalSavedSubRepliesCount,
                            intervalBetweenPage, ca, etaRelative, etaAt);
                        Console.Title = $"Archive progress: {finishedPageCount}/{totalPage} ETA: {etaRelative} {etaAt}";
                    });
                }
                _logger.LogInformation("Archive for {} posts({} threads, {} replies, {} subReplies) within all pages [1-{}] of forum {} finished",
                    totalSavedThreadsCount + totalSavedRepliesCount + totalSavedSubRepliesCount,
                    totalSavedThreadsCount, totalSavedRepliesCount, totalSavedSubRepliesCount, totalPage, _forumName);
            }
            catch (Exception e)
            {
                _logger.LogError(e, "Exception");
            }
        }

        private async Task<int> GetTotalPageForForum()
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            return (await scope1.Resolve<ThreadArchiveCrawler.New>()(_forumName).CrawlSinglePage(1))
                .Select(response => response.Result.Data.Page.TotalPage).Max();
        }

        private async Task<SaverChangeSet<ThreadPost>?> CrawlThreads(Page page, string forumName, Fid fid)
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var crawler = scope1.Resolve<ThreadArchiveCrawlFacade.New>()(fid, forumName);
            var savedThreads = (await crawler.CrawlPageRange(page, page)).SaveCrawled();
            if (savedThreads != null)
            {
                await scope1.Resolve<ThreadLateCrawlerAndSaver.New>()(fid)
                    .Crawl(savedThreads.NewlyAdded.ToDictionary(t => t.Tid, _ => (FailedCount)0));
            }
            return savedThreads;
        }

        private async Task<SavedRepliesKeyByTid> CrawlReplies(SaverChangeSet<ThreadPost>? savedThreads, Fid fid)
        {
            var shouldCrawlReplyTid = new HashSet<Tid>();
            var savedRepliesKeyByTid = new SavedRepliesKeyByTid();
            if (savedThreads == null) return savedRepliesKeyByTid;
            // some rare thread will have replyNum=0, but contains reply and can be revealed by requesting
            // we choose TO crawl these rare thread's replies for archive since most thread will have replies
            // following sql can figure out existing replies that not matched with parent thread's subReplyNum in db:
            // SELECT COUNT(*) FROM tbm_f{fid}_threads AS A INNER JOIN tbm_f{fid}_replies AS B ON A.tid = B.tid AND A.replyNum IS NULL
            savedThreads.AllAfter.ForEach(t => shouldCrawlReplyTid.Add(t.Tid));

            await Task.WhenAll(shouldCrawlReplyTid.Select(async tid =>
            {
                await using var scope1 = _scope0.BeginLifetimeScope();
                var crawler = scope1.Resolve<ReplyCrawlFacade.New>()(fid, tid);
                savedRepliesKeyByTid.SetIfNotNull(tid, (await crawler.CrawlPageRange(1)).SaveCrawled());
            }));
            return savedRepliesKeyByTid;
        }

        private async Task<int> CrawlSubReplies(SavedRepliesKeyByTid savedRepliesKeyByTid, Fid fid)
        {
            var shouldCrawlSubReplyPid = savedRepliesKeyByTid.Aggregate(new HashSet<(Tid, Pid)>(), (shouldCrawl, tidAndReplies) =>
            {
                var (tid, replies) = tidAndReplies;
                // some rare reply will have subReplyNum=0, but contains sub reply and can be revealed by requesting
                // we choose NOT TO crawl these rare reply's sub replies for archive since most reply won't have sub replies
                // following sql can figure out existing sub replies that not matched with parent reply's subReplyNum in db:
                // SELECT COUNT(*) FROM tbm_f{fid}97650_replies AS A INNER JOIN tbm_f{fid}_subReplies AS B ON A.pid = B.pid AND A.subReplyNum IS NULL
                replies.AllAfter.Where(r => r.SubReplyNum != null).ForEach(r => shouldCrawl.Add((tid, r.Pid)));
                return shouldCrawl;
            });
            var savedSubRepliesCount = 0;
            await Task.WhenAll(shouldCrawlSubReplyPid.Select(async tidAndPid =>
            {
                var (tid, pid) = tidAndPid;
                await using var scope1 = _scope0.BeginLifetimeScope();
                var saved = (await scope1.Resolve<SubReplyCrawlFacade.New>()(fid, tid, pid).CrawlPageRange(1)).SaveCrawled();
                if (saved == null) return;
                _ = Interlocked.Add(ref savedSubRepliesCount, saved.AllAfter.Count);
            }));
            return savedSubRepliesCount;
        }
    }
}
