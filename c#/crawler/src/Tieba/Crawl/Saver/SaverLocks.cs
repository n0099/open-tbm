namespace tbm.Crawler.Tieba.Crawl.Saver;

public sealed class SaverLocks<TKey>(ISet<TKey> globalLocked) : IDisposable
{
    private readonly List<TKey> _localLocked = [];

    public delegate SaverLocks<TKey> New(ISet<TKey> globalLocked);

    public void Dispose()
    {
        lock (globalLocked) globalLocked.ExceptWith(_localLocked);
        lock (_localLocked) _localLocked.Clear();
    }

    public IReadOnlyCollection<TKey> Acquire(IReadOnlyCollection<TKey> pendingLocking)
    {
        if (pendingLocking.Count == 0) return [];
        var newlyLocked = new List<TKey>(pendingLocking.Count);
        lock (globalLocked)
        {
            newlyLocked.AddRange(pendingLocking.Except(globalLocked));
            globalLocked.UnionWith(newlyLocked);
        }
        lock (_localLocked) _localLocked.AddRange(newlyLocked);
        return newlyLocked;
    }
}
