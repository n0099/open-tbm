namespace tbm.Crawler.Tieba.Crawl.Saver;

public class SaverLocks<TKey>
{
    public delegate T NewLocksFactory<out T>(IReadOnlySet<TKey> alreadyLocked);
    public delegate IEnumerable<TKey> LockingKeysSelector<in T>(T newlyLocked);

    private static readonly HashSet<TKey> GlobalLocks = [];
    private readonly List<TKey> _localLocks = [];

    public void AcquireLocksThen<TNewLock>(
        Action<IReadOnlyCollection<TNewLock>> payload,
        NewLocksFactory<IReadOnlyCollection<TNewLock>> newLocksFactory,
        LockingKeysSelector<IReadOnlyCollection<TNewLock>> lockingKeysSelector)
    {
        lock (GlobalLocks)
        {
            var newLocks = newLocksFactory(GlobalLocks);
            if (newLocks.Count == 0) return;
            _localLocks.AddRange(lockingKeysSelector(newLocks));
            GlobalLocks.UnionWith(_localLocks);
            payload(newLocks);
        }
    }

    public void ReleaseLocalLocked()
    {
        lock (GlobalLocks) GlobalLocks.ExceptWith(_localLocks);
    }
}
