namespace tbm.Crawler.Tieba.Crawl.Saver.Related;

public class ThreadLatestReplierSaver(
    ILogger<ThreadLatestReplierSaver> logger,
    SaverLocks<ThreadLatestReplierSaver.UniqueLatestReplier>.New latestReplierSaverLocksFactory,
    SaverLocks<Tid>.New tidSaverLocksFactory)
{
    private static readonly HashSet<UniqueLatestReplier> GlobalLockedLatestRepliers = [];
    private static readonly HashSet<Tid> GlobalLockedTids = [];

    private readonly Lazy<SaverLocks<UniqueLatestReplier>> _latestReplierSaverLocks =
        new(() => latestReplierSaverLocksFactory(GlobalLockedLatestRepliers));
    private readonly Lazy<SaverLocks<Tid>> _tidSaverLocks =
        new(() => tidSaverLocksFactory(GlobalLockedTids));

    public Action SaveFromThread(CrawlerDbContext db, IReadOnlyCollection<ThreadPost> threads)
    {
        var uniqueLatestRepliers = threads
            .Where(th => th.LatestReplier != null)
            .Select(UniqueLatestReplier.FromThread).ToList();
        var existingLatestRepliers = db.LatestRepliers.AsTracking().FilterByItems(
                uniqueLatestRepliers, (latestReplier, uniqueLatestReplier) =>
                    latestReplier.Name == uniqueLatestReplier.Name
                    && latestReplier.DisplayName == uniqueLatestReplier.DisplayName)
            .ToList();

        var threadsWithDuplicatedLatestReplier = (from existing in existingLatestRepliers
                join thread in threads
                    on UniqueLatestReplier.FromLatestReplier(existing) equals UniqueLatestReplier.FromThread(thread)
                join entityEntry in db.ChangeTracker.Entries<LatestReplier>()
                    on thread.LatestReplier equals entityEntry.Entity // Object.ReferenceEquals()
                select (existing, thread, entityEntry))

                // eager eval since detaching the temporary entity of a latest replier
                // that shared by other threads will mutate their latest replier to null
                .ToList();
        var newlyLocked = _tidSaverLocks.Value.Acquire(
            threadsWithDuplicatedLatestReplier.Select(t => t.thread.Tid));
        threadsWithDuplicatedLatestReplier.IntersectBy(newlyLocked, t => t.thread.Tid).ForEach(t =>
        {
            t.entityEntry.State = EntityState.Detached;
            t.thread.LatestReplier = t.existing;
        });

        return _tidSaverLocks.Value.Dispose;
    }

    public Action SaveFromUser(CrawlerDbContext db, Tid tid, IEnumerable<User> users)
    {
        var thread = db.Threads.AsTracking()
            .Include(th => th.LatestReplier)
            .SingleOrDefault(th => th.Tid == tid);
        if (thread == null) return () => { };

        // https://stackoverflow.com/questions/63094891/ef-core-tracking-child-objects-unnecessarily
        // detach the unused thread entity to prevent CrawlerDbContext.TimestampingEntities() updating its BasePost.LastSeenAt
        db.Entry(thread).State = EntityState.Detached;
        var threadLatestReplier = thread.LatestReplier;
        if (threadLatestReplier == null) return () => { };

        // possible race: two user swapped their name or displayName
        // within the timespan of crawling threads and crawling its (sub)replies
        // so the one later crawled is not the original latest replier of thread
        var matchedUsers = users
            .Where(u => u.Name == threadLatestReplier.Name
                        && u.DisplayName == threadLatestReplier.DisplayName)
            .DistinctBy(u => u.Uid).ToList();

        // ReSharper disable once ConvertIfStatementToSwitchStatement
        if (matchedUsers.Count == 0) return () => { };
        if (matchedUsers.Count > 1)
            Helper.LogDifferentValuesSharingTheSameKeyInEntities(logger, matchedUsers,
                $"{nameof(User.Name)} and {nameof(User.DisplayName)}",
                u => u.Uid, u => (u.Name, u.DisplayName));

        var user = matchedUsers[0];
        var newlyLocked = _latestReplierSaverLocks.Value.Acquire(
            [UniqueLatestReplier.FromLatestReplier(threadLatestReplier)]);
        if (newlyLocked.Count == 0 || threadLatestReplier.Uid == user.Uid)
            return () => { };
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
        return _latestReplierSaverLocks.Value.Dispose;
    }

    public record UniqueLatestReplier(string? Name, string? DisplayName)
    {
        public static UniqueLatestReplier FromLatestReplier(LatestReplier? latestReplier) =>
            new(latestReplier?.Name, latestReplier?.DisplayName);

        public static UniqueLatestReplier FromThread(ThreadPost thread) =>
            FromLatestReplier(thread.LatestReplier);
    }
}
