namespace tbm.Crawler.Tieba.Crawl.Saver;

public class SaverLocks<TKey>
{
    private static readonly HashSet<TKey> GlobalLocks = [];
    private readonly List<TKey> _localLocks = [];

    public IReadOnlyCollection<TKey> AcquireLocks(IReadOnlyCollection<TKey> pendingLocking)
    {
        if (pendingLocking.Count == 0) return [];
        var newlyLocked = new List<TKey>(pendingLocking.Count);
        lock (GlobalLocks)
        {
            newlyLocked.AddRange(GlobalLocks.Except(pendingLocking));
            GlobalLocks.UnionWith(newlyLocked);
        }
        _localLocks.AddRange(newlyLocked);
        return newlyLocked;
    }

    public void ReleaseLocalLocked()
    {
        lock (GlobalLocks) GlobalLocks.ExceptWith(_localLocks);
    }
}
