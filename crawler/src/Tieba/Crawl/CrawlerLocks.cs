using FailedCount = System.UInt16;

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
                _logger.LogTrace("Lock: type={} crawlingCount={} crawlingPagesCount={} failedCount={} failed={}", _postType,
                    _crawling.Count, JsonSerializer.Serialize(_crawling.ToDictionary(i => i.Key, i => i.Value.Count)),
                    _failed.Count, JsonSerializer.Serialize(_failed));
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
                var pagesLock = _crawling[index];
                lock (pagesLock)
                {
                    pagesLock.TryRemove(page, out _);
                    if (pagesLock.IsEmpty) _crawling.TryRemove(index, out _);
                }
            }
        }

        public void AcquireFailed(FidOrPostId index, Page page)
        {
            lock (_failed)
            {
                if (_failed.ContainsKey(index))
                {
                    var pagesLock = _failed[index];
                    lock (pagesLock) if (!pagesLock.TryAdd(page, 1)) pagesLock[page]++;
                }
                else
                {
                    var newPage = new ConcurrentDictionary<Page, FailedCount>();
                    newPage.TryAdd(page, 1);
                    _failed.TryAdd(index, newPage);
                }
            }
        }

        public Dictionary<FidOrPostId, Dictionary<Page, FailedCount>> RetryAllFailed()
        {
            lock (_failed)
            {
                var copyOfFailed = _failed.ToDictionary(p => p.Key, p =>
                {
                    lock (p.Value) return p.Value.ToDictionary(p2 => p2.Key, p2 => p2.Value);
                });
                _failed.Clear();
                return copyOfFailed;
            }
        }
    }
}
