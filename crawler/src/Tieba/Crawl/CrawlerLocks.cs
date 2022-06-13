namespace tbm.Crawler
{
#pragma warning disable IDE0058 // Expression value is never used
    public class CrawlerLocks : WithLogTrace
    {
        private readonly ConcurrentDictionary<FidOrPostId, ConcurrentDictionary<Page, Time>> _crawling = new();
        // inner value of field _failed with type ushort refers to failed times on this page and index before retry
        private readonly ConcurrentDictionary<FidOrPostId, ConcurrentDictionary<Page, FailedCount>> _failed = new();
        private readonly ILogger<CrawlerLocks> _logger;
        private readonly IConfigurationSection _config;
        private readonly string _postType;

        public delegate CrawlerLocks New(string postType);

        public CrawlerLocks(ILogger<CrawlerLocks> logger, IConfiguration config, string postType)
        {
            _logger = logger;
            _config = config.GetSection($"CrawlerLocks:{postType}");
            _postType = postType;
            InitLogTrace(_config);
        }

        protected override void LogTrace()
        {
            if (!ShouldLogTrace()) return;
            lock (_crawling)
            lock (_failed)
            {
                var crawlingWithoutEmpty = _crawling.Where(i => !i.Value.IsEmpty).ToList();
                _logger.LogTrace("Lock: type={} crawlingCount={} crawlingPagesCount={} failedCount={} failed={}", _postType,
                    crawlingWithoutEmpty.Count, Helper.UnescapedJsonSerialize(crawlingWithoutEmpty.ToDictionary(i => i.Key, i => i.Value.Count)),
                    _failed.Count, Helper.UnescapedJsonSerialize(_failed));
            }
        }

        public IEnumerable<Page> AcquireRange(FidOrPostId index, IEnumerable<Page> pages)
        {
            var lockFreePages = pages.ToHashSet();
            lock (_crawling)
            { // lock the entire ConcurrentDictionary since following bulk insert should be a single atomic operation
                var now = (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
                if (!_crawling.ContainsKey(index))
                { // if no one is locking any page in index, just insert pages then return it as is
                    var pageTimeDict = lockFreePages.Select(p => KeyValuePair.Create(p, now));
                    var newPage = new ConcurrentDictionary<Page, Time>(pageTimeDict);
                    if (_crawling.TryAdd(index, newPage)) return lockFreePages;
                }
                lockFreePages.ToList().ForEach(page => // iterate on copy in order to mutate the original lockFreePages
                {
                    var pagesLock = _crawling[index];
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

        public void ReleaseLock(FidOrPostId index, Page page)
        {
            lock (_crawling)
            {
                if (!_crawling.TryGetValue(index, out var pagesLock))
                {
                    _logger.LogWarning("Try to release a crawling page lock {} in {} id {} more than once", page, _postType, index);
                    return;
                }
                lock (pagesLock)
                {
                    pagesLock.TryRemove(page, out _);
                    if (pagesLock.IsEmpty) _crawling.TryRemove(index, out _);
                }
            }
        }

        public void AcquireFailed(FidOrPostId index, Page page, FailedCount failedCount)
        {
            var maxRetry = _config.GetValue<FailedCount>("MaxRetryTimes", 5);
            if (failedCount >= maxRetry)
            {
                _logger.LogInformation("Retry for previous failed crawling of page {} in {} id {} has been canceled since it's reaching the configured max retry times {}", page, _postType, index, maxRetry);
                return;
            }
            lock (_failed)
            {
                if (_failed.ContainsKey(index))
                {
                    var pagesLock = _failed[index];
                    lock (pagesLock) if (!pagesLock.TryAdd(page, failedCount)) pagesLock[page] = failedCount;
                }
                else
                {
                    var newPage = new ConcurrentDictionary<Page, FailedCount> { [page] = failedCount };
                    _failed.TryAdd(index, newPage);
                }
            }
        }

        public Dictionary<FidOrPostId, List<PageAndFailedCount>> RetryAllFailed()
        {
            lock (_failed)
            {
                var copyOfFailed = _failed.ToDictionary(p => p.Key, p =>
                {
                    lock (p.Value) return p.Value.Select(pair => new PageAndFailedCount(pair.Key, pair.Value)).ToList();
                });
                _failed.Clear();
                return copyOfFailed;
            }
        }

        public record PageAndFailedCount(Page Page, FailedCount FailedCount);
    }
}
