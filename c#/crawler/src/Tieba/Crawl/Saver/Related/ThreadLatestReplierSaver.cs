namespace tbm.Crawler.Tieba.Crawl.Saver.Related;

public class ThreadLatestReplierSaver(SaverLocks<ThreadLatestReplierSaver.UniqueLatestReplier>.New saverLocksFactory)
{
    private static readonly HashSet<UniqueLatestReplier> GlobalLockedLatestRepliers = [];
    private readonly Lazy<SaverLocks<UniqueLatestReplier>> _saverLocks =
        new(() => saverLocksFactory(GlobalLockedLatestRepliers));

    public Action SaveFromThread(CrawlerDbContext db, IReadOnlyCollection<ThreadPost> threads)
    {
        var uniqueLatestRepliers = threads
            .Where(th => th.LatestReplier != null)
            .Select(UniqueLatestReplier.FromThread).ToList();
        var existingLatestRepliers = db.LatestRepliers.AsNoTracking().FilterByItems(
            uniqueLatestRepliers, (latestReplier, uniqueLatestReplier) =>
                latestReplier.Name == uniqueLatestReplier.Name
                && latestReplier.DisplayName == uniqueLatestReplier.DisplayName)
            .ToList();
        (from existing in existingLatestRepliers
                join thread in threads
                    on UniqueLatestReplier.FromLatestReplier(existing) equals UniqueLatestReplier.FromThread(thread)
                select (existing, thread))
            .ForEach(t => t.thread.LatestReplier = t.existing);

        _ = _saverLocks.Value.Acquire(uniqueLatestRepliers
            .Except(existingLatestRepliers.Select(UniqueLatestReplier.FromLatestReplier))
            .ToList());
        return _saverLocks.Value.Dispose;
    }

    public record UniqueLatestReplier(string? Name, string? DisplayName)
    {
        public static UniqueLatestReplier FromLatestReplier(LatestReplier? latestReplier) =>
            new(latestReplier?.Name, latestReplier?.DisplayName);

        public static UniqueLatestReplier FromThread(ThreadPost thread) =>
            FromLatestReplier(thread.LatestReplier);
    }
}
