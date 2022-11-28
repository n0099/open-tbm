namespace tbm.Crawler
{
    public class CrawlerLocks : WithLogTrace
    {
        public record LockId(Fid Fid, Tid? Tid = null, Pid? Pid = null);
        private readonly ConcurrentDictionary<LockId, ConcurrentDictionary<Page, Time>> _crawling = new();
        // inner value of field _failed with type ushort refers to failed times on this page and lockId before retry
        private readonly ConcurrentDictionary<LockId, ConcurrentDictionary<Page, FailedCount>> _failed = new();
        private readonly ILogger<CrawlerLocks> _logger;
        private readonly IConfigurationSection _config;
        public string LockType { get; }

        public CrawlerLocks(ILogger<CrawlerLocks> logger, IConfiguration config, string lockType)
        {
            _logger = logger;
            _config = config.GetSection($"CrawlerLocks:{lockType}");
            LockType = lockType;
            InitLogTrace(_config);
        }

        protected override void LogTrace()
        {
            if (!ShouldLogTrace()) return;
            lock (_crawling)
            lock (_failed)
            {
                _logger.LogTrace("Lock: type={} crawlingIdsCount={} crawlingPagesCount={} crawlingPagesCountKeyById={} failedIdsCount={} failedPagesCount={} failedAll={}", LockType,
                    _crawling.Count, _crawling.Values.Select(d => d.Count).Sum(),
                    Helper.UnescapedJsonSerialize(_crawling.ToDictionary(i => i.Key, i => i.Value.Count)),
                    _failed.Count, _failed.Values.Select(d => d.Count).Sum(), Helper.UnescapedJsonSerialize(_failed));
            }
        }

        public IEnumerable<Page> AcquireRange(LockId lockId, IEnumerable<Page> pages)
        {
            var lockFreePages = pages.ToHashSet();
            lock (_crawling)
            { // lock the entire ConcurrentDictionary since following bulk insert should be a single atomic operation
                var now = (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
                if (!_crawling.ContainsKey(lockId))
                { // if no one is locking any page in lockId, just insert pages then return it as is
                    var pageTimeDict = lockFreePages.Select(i => KeyValuePair.Create(i, now));
                    var newPage = new ConcurrentDictionary<Page, Time>(pageTimeDict);
                    if (_crawling.TryAdd(lockId, newPage)) return lockFreePages;
                }
                lockFreePages.ToList().ForEach(page => // iterate on copy in order to mutate the original lockFreePages
                {
                    var pagesLock = _crawling[lockId];
                    lock (pagesLock)
                    {
                        if (pagesLock.TryAdd(page, now)) return;
                        // when page is locking:
                        var lockTimeout = _config.GetValue<Time>("LockTimeoutSec", 300); // 5 minutes;
                        if (pagesLock[page] < now - lockTimeout)
                            pagesLock[page] = now;
                        else lockFreePages.Remove(page);
                    }
                });
            }

            return lockFreePages;
        }

        public void ReleaseRange(LockId lockId, IEnumerable<Page> pages)
        {
            lock (_crawling)
            {
                if (!_crawling.TryGetValue(lockId, out var pagesLock))
                {
                    _logger.LogWarning("Try to release a crawling page lock {} in {} id {} more than once", pages, LockType, lockId);
                    return;
                }
                lock (pagesLock)
                {
                    pages.ForEach(i => pagesLock.TryRemove(i, out _));
                    if (pagesLock.IsEmpty) _crawling.TryRemove(lockId, out _);
                }
            }
        }

        public void AcquireFailed(LockId lockId, Page page, FailedCount failedCount)
        {
            var maxRetry = _config.GetValue<FailedCount>("MaxRetryTimes", 5);
            if (failedCount >= maxRetry)
            {
                _logger.LogInformation("Retry for previous failed crawling of page {} in {} id {} has been canceled since it's reaching the configured max retry times {}", page, LockType, lockId, maxRetry);
                return;
            }
            lock (_failed)
            {
                if (_failed.ContainsKey(lockId))
                {
                    var pagesLock = _failed[lockId];
                    lock (pagesLock) if (!pagesLock.TryAdd(page, failedCount)) pagesLock[page] = failedCount;
                }
                else
                {
                    var newPage = new ConcurrentDictionary<Page, FailedCount> { [page] = failedCount };
                    _failed.TryAdd(lockId, newPage);
                }
            }
        }

        public Dictionary<LockId, Dictionary<Page, FailedCount>> RetryAllFailed()
        {
            lock (_failed)
            {
                var copyOfFailed = _failed.ToDictionary(i => i.Key, i =>
                {
                    lock (i.Value) return new Dictionary<Page, FailedCount>(i.Value);
                });
                _failed.Clear();
                return copyOfFailed;
            }
        }
    }
}
