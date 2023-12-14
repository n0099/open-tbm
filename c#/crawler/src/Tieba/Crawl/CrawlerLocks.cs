namespace tbm.Crawler.Tieba.Crawl;

public class CrawlerLocks(ILogger<CrawlerLocks> logger, IConfiguration config, string lockType)
    : WithLogTrace(config, $"CrawlerLocks:{lockType}")
{
    private readonly IConfigurationSection _config = config.GetSection($"CrawlerLocks:{lockType}");
    private readonly ConcurrentDictionary<LockId, ConcurrentDictionary<Page, Time>> _crawling = new();

    // inner value of field _failed with type ushort refers to failed times on this page and lockId before retry
    private readonly ConcurrentDictionary<LockId, ConcurrentDictionary<Page, FailureCount>> _failed = new();

    public static List<string> RegisteredCrawlerLocks { get; } = new() {"thread", "threadLate", "reply", "subReply"};
    public string LockType { get; } = lockType;

    public HashSet<Page> AcquireRange(LockId lockId, IEnumerable<Page> pages)
    {
        var acquiredPages = pages.ToHashSet();
        lock (_crawling)
        { // lock the entire ConcurrentDictionary since following bulk insert should be a single atomic operation
            Helper.GetNowTimestamp(out var now);
            if (!_crawling.ContainsKey(lockId))
            { // if no one is locking any page in lockId, just insert pages then return it as is
                var pageTimeDict = acquiredPages.Select(page => KeyValuePair.Create(page, now));
                var newPage = new ConcurrentDictionary<Page, Time>(pageTimeDict);
                if (_crawling.TryAdd(lockId, newPage)) return acquiredPages;
            }
            var pagesLock = _crawling[lockId];
            lock (pagesLock)
            { // iterate on a shallow copy to mutate the original acquiredPages
                foreach (var page in acquiredPages.ToList())
                {
                    if (pagesLock.TryAdd(page, now)) continue;

                    // when page is locking
                    var lockTimeout = _config.GetValue<Time>("LockTimeoutSec", 300); // 5 minutes;
                    if (pagesLock[page] < now - lockTimeout)
                        pagesLock[page] = now;
                    else _ = acquiredPages.Remove(page);
                }
            }
        }

        return acquiredPages;
    }

    public void ReleaseRange(LockId lockId, IEnumerable<Page> pages)
    {
        lock (_crawling)
        {
            if (!_crawling.TryGetValue(lockId, out var pagesLock))
            {
                logger.LogWarning("Try to release a crawling page lock {} in {} id {} more than once",
                    pages, LockType, lockId);
                return;
            }
            lock (pagesLock)
            {
                pages.ForEach(page => pagesLock.TryRemove(page, out _));
                if (pagesLock.IsEmpty) _ = _crawling.TryRemove(lockId, out _);
            }
        }
    }

    public void AcquireFailed(LockId lockId, Page page, FailureCount failureCount)
    {
        var maxRetry = _config.GetValue<FailureCount>("MaxRetryTimes", 5);
        if (failureCount >= maxRetry)
        {
            logger.LogInformation("Retry for previous failed crawling of page {} in {} id {} has been canceled since it's reaching the configured max retry times {}",
                page, LockType, lockId, maxRetry);
            return;
        }
        lock (_failed)
        {
            if (_failed.TryGetValue(lockId, out var pagesLock))
            {
                lock (pagesLock) if (!pagesLock.TryAdd(page, failureCount)) pagesLock[page] = failureCount;
            }
            else
            {
                var newPage = new ConcurrentDictionary<Page, FailureCount> {[page] = failureCount};
                _ = _failed.TryAdd(lockId, newPage);
            }
        }
    }

    public Dictionary<LockId, Dictionary<Page, FailureCount>> RetryAllFailed()
    {
        lock (_failed)
        {
            var deepCloneOfFailed = _failed.ToDictionary(pair => pair.Key, pair =>
            {
                lock (pair.Value) return new Dictionary<Page, FailureCount>(pair.Value);
            });
            _failed.Clear();
            return deepCloneOfFailed;
        }
    }

    protected override void LogTrace()
    {
        if (!ShouldLogTrace()) return;
        lock (_crawling)
        lock (_failed)
        {
            logger.LogTrace("Lock: type={} crawlingIdCount={} crawlingPageCount={} crawlingPageCountsKeyById={} failedIdCount={} failedPageCount={} failures={}", LockType,
                _crawling.Count, _crawling.Values.Select(d => d.Count).Sum(),
                Helper.UnescapedJsonSerialize(_crawling.ToDictionary(pair => pair.Key.ToString(), pair => pair.Value.Count)),
                _failed.Count, _failed.Values.Select(d => d.Count).Sum(),
                Helper.UnescapedJsonSerialize(_failed.ToDictionary(pair => pair.Key.ToString(), pair => pair.Value)));
        }
    }

    public record LockId(Fid Fid, Tid? Tid = null, Pid? Pid = null)
    {
        public override string ToString() =>
            $"f{Fid}{(Tid == null ? "" : $" t{Tid}")}{(Pid == null ? "" : $" p{Pid}")}";
    }
}
