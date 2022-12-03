namespace tbm.Crawler
{
    using SavedThreadsList = List<SaverChangeSet<ThreadPost>>;
    using SavedRepliesKeyByTid = ConcurrentDictionary<Tid, SaverChangeSet<ReplyPost>>;

    public class MainCrawlWorker : CyclicCrawlWorker
    {
        private readonly ILogger<MainCrawlWorker> _logger;
        private readonly ILifetimeScope _scope0;
        // store the max latestReplyTime of threads appeared in the previous crawl worker, key by fid
        private readonly Dictionary<Fid, Time> _latestReplyTimeCheckpointCache = new();

        public MainCrawlWorker(ILogger<MainCrawlWorker> logger, IConfiguration config,
            ILifetimeScope scope0, IIndex<string, CrawlerLocks> locks) : base(config)
        {
            _logger = logger;
            _scope0 = scope0;
            _ = SyncCrawlIntervalWithConfig();
            // eager initial all keyed CrawlerLocks singleton instances, in order to sync their timer of WithLogTrace
            _ = locks["thread"];
            _ = locks["threadLate"];
            _ = locks["reply"];
            _ = locks["subReply"];
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            Timer.Elapsed += async (_, _) => await CrawlThenSave();
            await CrawlThenSave();
        }

        private record FidAndName(Fid Fid, string Name);

        private async IAsyncEnumerable<FidAndName> ForumGenerator()
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<TbmDbContext.New>()(0);
            var forums = (from f in db.ForumsInfo where f.IsCrawling select new FidAndName(f.Fid, f.Name)).ToList();
            var yieldInterval = SyncCrawlIntervalWithConfig() / (float)forums.Count;
            foreach (var fidAndName in forums)
            {
                yield return fidAndName;
                await Task.Delay((int)(yieldInterval * 1000));
            }
        }

        private async Task CrawlThenSave()
        {
            try
            {
                await foreach (var (fid, forumName) in ForumGenerator())
                    await CrawlSubReplies(await CrawlReplies(await CrawlThreads(forumName, fid), fid), fid);
            }
            catch (Exception e)
            {
                _logger.LogError(e, "Exception");
            }
        }

        private async Task<SavedThreadsList> CrawlThreads(string forumName, Fid fid)
        {
            var savedThreads = new SavedThreadsList();
            Time minLatestReplyTime;
            Page crawlingPage = 0;
            await using var scope1 = _scope0.BeginLifetimeScope();
            if (!_latestReplyTimeCheckpointCache.TryGetValue(fid, out var maxLatestReplyTimeInPreviousCrawl))
                // get the largest value of field latestReplyTime in all stored threads of this forum
                // this approach is not as accurate as extracting the last thread in the response list and needs a full table scan on db
                // https://stackoverflow.com/questions/341264/max-or-default
                maxLatestReplyTimeInPreviousCrawl = scope1.Resolve<TbmDbContext.New>()(fid).Threads.Max(t => (Time?)t.LatestReplyTime) ?? Time.MaxValue;
            do
            {
                crawlingPage++;
                await using var scope2 = scope1.BeginLifetimeScope();
                var crawler = scope2.Resolve<ThreadCrawlFacade.New>()(fid, forumName);
                var currentPageChangeSet = (await crawler.CrawlPageRange(crawlingPage, crawlingPage)).SaveCrawled();
                if (currentPageChangeSet != null)
                {
                    savedThreads.Add(currentPageChangeSet);
                    var latestReplyTimes = currentPageChangeSet.AllAfter.Select(t => t.LatestReplyTime).ToList();
                    minLatestReplyTime = latestReplyTimes.Min();
                    if (crawlingPage == 1) _latestReplyTimeCheckpointCache[fid] = latestReplyTimes.Max();
                }
                else
                { // retry this page
                    crawlingPage--;
                    minLatestReplyTime = Time.MaxValue;
                }
            } while (minLatestReplyTime > maxLatestReplyTimeInPreviousCrawl);

            await Task.WhenAll(savedThreads.Select(async threads =>
            {
                await using var scope3 = _scope0.BeginLifetimeScope();
                await scope3.Resolve<ThreadLateCrawlerAndSaver.New>()(fid)
                    .Crawl(threads.NewlyAdded.ToDictionary(t => t.Tid, _ => (FailureCount)0));
            }));

            return savedThreads;
        }

        private Task<SavedRepliesKeyByTid> CrawlReplies(SavedThreadsList savedThreads, Fid fid) => CrawlReplies(savedThreads, fid, _scope0);

        public static async Task<SavedRepliesKeyByTid> CrawlReplies(SavedThreadsList savedThreads, Fid fid, ILifetimeScope scope)
        {
            var shouldCrawlReplyTid = savedThreads.Aggregate(new HashSet<Tid>(), (shouldCrawl, threads) =>
            {
                threads.NewlyAdded.ForEach(t => shouldCrawl.Add(t.Tid));
                threads.Existing.ForEach(beforeAndAfter =>
                {
                    var (before, after) = beforeAndAfter;
                    if (before.ReplyNum != after.ReplyNum
                        || before.LatestReplyTime != after.LatestReplyTime
                        || before.LatestReplierUid != after.LatestReplierUid)
                        _ = shouldCrawl.Add(before.Tid);
                });
                return shouldCrawl;
            });
            var savedRepliesKeyByTid = new SavedRepliesKeyByTid();
            await Task.WhenAll(shouldCrawlReplyTid.Select(async tid =>
            {
                await using var scope1 = scope.BeginLifetimeScope();
                var crawler = scope1.Resolve<ReplyCrawlFacade.New>()(fid, tid);
                savedRepliesKeyByTid.SetIfNotNull(tid, (await crawler.CrawlPageRange(1)).SaveCrawled());
            }));
            return savedRepliesKeyByTid;
        }

        private Task CrawlSubReplies(SavedRepliesKeyByTid savedRepliesKeyByTid, Fid fid) => CrawlSubReplies(savedRepliesKeyByTid, fid, _scope0);

        public static async Task CrawlSubReplies(IDictionary<Tid, SaverChangeSet<ReplyPost>> savedRepliesKeyByTid, Fid fid, ILifetimeScope scope)
        {
            var shouldCrawlSubReplyPid = savedRepliesKeyByTid.Aggregate(new HashSet<(Tid, Pid)>(), (shouldCrawl, tidAndReplies) =>
            {
                var (tid, replies) = tidAndReplies;
                replies.NewlyAdded.ForEach(r =>
                {
                    if (r.SubReplyNum != null) _ = shouldCrawl.Add((tid, r.Pid));
                });
                replies.Existing.ForEach(beforeAndAfter =>
                {
                    var (before, after) = beforeAndAfter;
                    if (after.SubReplyNum != null && before.SubReplyNum != after.SubReplyNum) _ = shouldCrawl.Add((tid, before.Pid));
                });
                return shouldCrawl;
            });
            await Task.WhenAll(shouldCrawlSubReplyPid.Select(async tidAndPid =>
            {
                var (tid, pid) = tidAndPid;
                await using var scope1 = scope.BeginLifetimeScope();
                _ = (await scope1.Resolve<SubReplyCrawlFacade.New>()(fid, tid, pid).CrawlPageRange(1)).SaveCrawled();
            }));
        }
    }
}
