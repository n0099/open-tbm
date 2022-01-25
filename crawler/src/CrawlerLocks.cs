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
        private readonly ushort _retryAfter;
        private readonly ushort _newPageConcurrencyLevel;
        private readonly ushort _newPageCapacity;
        private ConcurrentDictionary<FidOrPostID, ConcurrentDictionary<Page, Time>> Crawling { get; }
        private ConcurrentDictionary<FidOrPostID, ConcurrentDictionary<Page, ushort>> Failed { get; }

        public delegate CrawlerLocks New(string postType);

        public CrawlerLocks(IConfiguration config, string postType)
        {
            var locksConfig = config.GetSection($"CrawlerLocks:{postType}");
            _retryAfter = locksConfig.GetValue<ushort>("RetryAfterSec", 300); // 5 minutes
            var concurrencyLevels = locksConfig.GetSection("ConcurrencyLevel");
            var initialCapacities = locksConfig.GetSection("InitialCapacity");
            _newPageConcurrencyLevel = concurrencyLevels.GetValue<ushort>("NewPage", 5);
            _newPageCapacity = initialCapacities.GetValue<ushort>("NewPage", 5);
            Crawling = new(concurrencyLevels.GetValue("Crawling", 10), initialCapacities.GetValue("Crawling", 20));
            Failed = new(concurrencyLevels.GetValue("Failed", 10), initialCapacities.GetValue("Failed", 20));
        }

        public IEnumerable<Page> AddLocks(FidOrPostID index, IEnumerable<Page> pages)
        {
            var lockFreePages = pages.ToHashSet();
            lock (Crawling)
            { // lock the entire ConcurrentDictionary since following bulk insert should be a single atomic operation
                var now = (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
                if (!Crawling.ContainsKey(index))
                { // if no one is locking any pages in index, just insert pages then return it as is
                    var pageTimeDict = lockFreePages.Select(p => new KeyValuePair<Page, Time>(p, now));
                    var newFid = new ConcurrentDictionary<Page, Time>(_newPageConcurrencyLevel, pageTimeDict, null);
                    if (Crawling.TryAdd(index, newFid)) return lockFreePages;
                }
                foreach (var page in lockFreePages.ToList()) // iterate on copy
                {
                    if (Crawling[index].TryAdd(page, now)) continue;
                    // when page in locking:
                    if (Crawling[index][page] < now - _retryAfter)
                        Crawling[index][page] = now;
                    else lockFreePages.Remove(page);
                }
            }

            return lockFreePages;
        }

        public void AddFailed(FidOrPostID index, Page page)
        {
            var newFid = new ConcurrentDictionary<Page, ushort>(_newPageConcurrencyLevel, _newPageCapacity);
            newFid.TryAdd(page, 1);
            if (Failed.TryAdd(index, newFid)) return;

            lock (Failed)
                if (!Failed[index].TryAdd(page, 1))
                    Failed[index][page]++;
        }

        public void ReleaseLock(FidOrPostID index, Page page)
        {
            Crawling[index].TryRemove(page, out _);
            lock (Crawling)
                if (Crawling[index].IsEmpty)
                    Crawling.TryRemove(index, out _);
        }
    }
}
