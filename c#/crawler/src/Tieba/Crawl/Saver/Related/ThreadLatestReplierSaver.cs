namespace tbm.Crawler.Tieba.Crawl.Saver.Related;

public class ThreadLatestReplierSaver(
    ILogger<ThreadLatestReplierSaver> logger,
    SaverLocks<ThreadLatestReplierSaver.UniqueLatestReplier>.New saverLocksFactory)
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

    public Action SaveFromUser(CrawlerDbContext db, Tid tid, IEnumerable<User> users)
    {
        var threadLatestReplier = db.Threads.AsTracking()
            .Include(th => th.LatestReplier)
            .SingleOrDefault(th => th.Tid == tid)?.LatestReplier;
        if (threadLatestReplier == null) return () => { };

        // possible race: two user swapped their name or displayName
        // within the timespan of crawling threads and crawling its (sub)replies
        // so the one later crawled is not the original latest replier of thread
        var matchedUsers = users
            .Where(u => u.Name == threadLatestReplier.Name
                        && u.DisplayName == threadLatestReplier.DisplayName)
            .DistinctBy(u => u.Uid).ToList();
        if (matchedUsers.Count == 0) return () => { };
        if (matchedUsers.Count > 1)
            Helper.LogDifferentValuesSharingTheSameKeyInEntities(logger, matchedUsers,
                $"{nameof(User.Name)} and {nameof(User.DisplayName)}",
                u => u.Uid, u => (u.Name, u.DisplayName));

        var user = matchedUsers[0];
        if (threadLatestReplier.Uid == user.Uid) return () => { };
        if (threadLatestReplier.Uid != null)
            _ = db.LatestReplierRevisions.Add(new()
            {
                TakenAt = threadLatestReplier.UpdatedAt ?? threadLatestReplier.CreatedAt,
                Id = threadLatestReplier.Id,
                Uid = threadLatestReplier.Uid.Value,
                Name = threadLatestReplier.Name,
                DisplayName = threadLatestReplier.DisplayName
            });

        threadLatestReplier.Uid = user.Uid;
        _ = _saverLocks.Value.Acquire([UniqueLatestReplier.FromLatestReplier(threadLatestReplier)]);
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
