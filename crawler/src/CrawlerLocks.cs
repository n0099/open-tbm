using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using Microsoft.Extensions.Configuration;
using FidOrPostID = System.UInt64;
using Page = System.UInt32;
using Time = System.UInt32;

namespace tbm
{
    public class CrawlerLocks
    {
        private readonly ConcurrentDictionary<FidOrPostID, ConcurrentDictionary<Page, Time>> _crawling = new();
        private readonly ConcurrentDictionary<FidOrPostID, ConcurrentDictionary<Page, ushort>> _failed = new();
        private readonly ushort _retryAfter;

        public delegate CrawlerLocks New(string postType);

        public CrawlerLocks(IConfiguration config, string postType)
        {
            var locksConfig = config.GetSection($"CrawlerLocks:{postType}");
            _retryAfter = locksConfig.GetValue<ushort>("RetryAfterSec", 300); // 5 minutes
        }

        public IEnumerable<Page> AddLocks(FidOrPostID index, IEnumerable<Page> pages)
        {
            var lockFreePages = pages.ToHashSet();
            lock (_crawling)
            { // lock the entire ConcurrentDictionary since following bulk insert should be a single atomic operation
                var now = (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
                if (!_crawling.ContainsKey(index))
                { // if no one is locking any pages in index, just insert pages then return it as is
                    var pageTimeDict = lockFreePages.Select(p => new KeyValuePair<Page, Time>(p, now));
                    var newFid = new ConcurrentDictionary<Page, Time>(pageTimeDict);
                    if (_crawling.TryAdd(index, newFid)) return lockFreePages;
                }
                foreach (var page in lockFreePages.ToList()) // iterate on copy
                {
                    if (_crawling[index].TryAdd(page, now)) continue;
                    // when page in locking:
                    if (_crawling[index][page] < now - _retryAfter)
                        _crawling[index][page] = now;
                    else lockFreePages.Remove(page);
                }
            }

            return lockFreePages;
        }

        public void AddFailed(FidOrPostID index, Page page)
        {
            var newFid = new ConcurrentDictionary<Page, ushort>();
            newFid.TryAdd(page, 1);
            if (_failed.TryAdd(index, newFid)) return;

            lock (_failed)
                if (!_failed[index].TryAdd(page, 1))
                    _failed[index][page]++;
        }

        public void ReleaseLock(FidOrPostID index, Page page)
        {
            _crawling[index].TryRemove(page, out _);
            lock (_crawling)
                if (_crawling[index].IsEmpty)
                    _crawling.TryRemove(index, out _);
        }
    }
}
