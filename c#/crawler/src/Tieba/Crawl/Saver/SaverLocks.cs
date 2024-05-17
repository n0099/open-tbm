namespace tbm.Crawler.Tieba.Crawl.Saver;

public sealed class SaverLocks<TKey> : IDisposable
{
    private static readonly HashSet<TKey> GlobalLocks = [];
    private readonly List<TKey> _localLocks = [];

    public void Dispose()
    {
        lock (GlobalLocks) GlobalLocks.ExceptWith(_localLocks);
        lock (_localLocks) _localLocks.Clear();
    }

    public IReadOnlyCollection<TKey> AcquireLocks(IReadOnlyCollection<TKey> pendingLocking)
    {
        if (pendingLocking.Count == 0) return [];
        var newlyLocked = new List<TKey>(pendingLocking.Count);
        lock (GlobalLocks)
        {
            newlyLocked.AddRange(pendingLocking.Except(GlobalLocks));
            GlobalLocks.UnionWith(newlyLocked);
        }
        lock (_localLocks) _localLocks.AddRange(newlyLocked);
        return newlyLocked;
    }
}
