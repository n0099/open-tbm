using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using FidOrPostID = System.UInt64;
using Page = System.UInt32;
using Time = System.UInt32;

namespace tbm
{
    public record CrawlerLocks(
        ushort RetryAfter = 300, // 5 minutes
        ushort ConcurrencyLevel = 5,
        ushort FailedInitialCapacity = 10
    )
    {
        private ConcurrentDictionary<FidOrPostID, ConcurrentDictionary<Page, Time>> Crawling { get; } = new(10, 20);
        private ConcurrentDictionary<FidOrPostID, ConcurrentDictionary<Page, ushort>> Failed { get; } = new(10, 20);

        public IEnumerable<Page> AddLocks(FidOrPostID index, IEnumerable<Page> pages)
        {
            var lockFreePages = pages.ToHashSet();
            lock (Crawling)
            { // lock the entire ConcurrentDictionary since following bulk insert should be a single atomic operation
                var now = (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
                if (!Crawling.ContainsKey(index))
                { // if no one is locking any pages in index, just insert pages then return it as is
                    var pageTimeDict = lockFreePages.Select(p => new KeyValuePair<Page, Time>(p, now));
                    var newFid = new ConcurrentDictionary<Page, Time>(ConcurrencyLevel, pageTimeDict, null);
                    if (Crawling.TryAdd(index, newFid)) return lockFreePages;
                }
                foreach (var page in lockFreePages.ToList()) // iterate on copy
                {
                    if (Crawling[index].TryAdd(page, now)) continue;
                    // when page in locking:
                    if (Crawling[index][page] < now - RetryAfter)
                        Crawling[index][page] = now;
                    else lockFreePages.Remove(page);
                }
            }

            return lockFreePages;
        }

        public void AddFailed(FidOrPostID index, Page page)
        {
            var newFid = new ConcurrentDictionary<Page, ushort>(ConcurrencyLevel, FailedInitialCapacity);
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
