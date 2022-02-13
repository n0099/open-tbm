using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;
using FidOrPostID = System.UInt64;
using Page = System.UInt32;
using Time = System.UInt32;

namespace tbm.Crawler
{
    public class CrawlerLocks : WithLogTrace
    {
        private readonly ConcurrentDictionary<FidOrPostID, ConcurrentDictionary<Page, Time>> _crawling = new();
        private readonly ConcurrentDictionary<FidOrPostID, ConcurrentDictionary<Page, ushort>> _failed = new();
        private readonly IConfigurationSection _config;
        private readonly ILogger<CrawlerLocks> _logger;
        private readonly string _postType;

        public delegate CrawlerLocks New(string postType);

        public CrawlerLocks(IConfiguration config, ILogger<CrawlerLocks> logger, string postType)
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

        public IEnumerable<Page> AcquireRange(FidOrPostID index, IEnumerable<Page> pages)
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
                foreach (var page in lockFreePages.ToList()) // iterate on copy
                {
                    if (_crawling[index].TryAdd(page, now)) continue;
                    // when page is locking:
                    var lockTimeout = _config.GetValue<ushort>("LockTimeoutSec", 300); // 5 minutes;
                    if (_crawling[index][page] < now - lockTimeout)
                        _crawling[index][page] = now;
                    else lockFreePages.Remove(page);
                }
            }

            return lockFreePages;
        }

        public void AcquireFailed(FidOrPostID index, Page page)
        {
            var newPage = new ConcurrentDictionary<Page, ushort>();
            newPage.TryAdd(page, 1);
            lock (_failed)
            {
                if (_failed.TryAdd(index, newPage)) return;
                if (!_failed[index].TryAdd(page, 1)) _failed[index][page]++;
            }
        }

        public void ReleaseLock(FidOrPostID index, Page page)
        {
            lock (_crawling)
            {
                _crawling[index].TryRemove(page, out _);
                if (_crawling[index].IsEmpty) _crawling.TryRemove(index, out _);
            }
        }
    }
}
