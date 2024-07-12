namespace tbm.Crawler.Tieba.Crawl.Saver;

public class SaverChangeSet<TPost>(
    Func<TPost, PostId> postIdSelector,
    IReadOnlyCollection<TPost> existingBefore,
    ICollection<TPost> existingAfterAndNewlyAdded,
    IReadOnlyCollection<TPost> existingAfterInTracking)
    where TPost : BasePost
{
    public IReadOnlyCollection<(TPost Before, TPost After)> Existing { get; } = existingBefore
        .OrderBy(postIdSelector)
        .EquiZip(existingAfterInTracking
                .IntersectBy(existingBefore.Select(postIdSelector), postIdSelector)
                .OrderBy(postIdSelector),
            (before, after) => (before, after))
        .ToList().AsReadOnly();

    public IReadOnlyCollection<TPost> NewlyAdded { get; } = existingAfterAndNewlyAdded
        .ExceptBy(existingBefore.Select(postIdSelector), postIdSelector)
        .ToList().AsReadOnly();

    // https://stackoverflow.com/questions/3404975/left-outer-join-in-linq/23558389#23558389
    public IReadOnlyCollection<TPost> AllAfter { get; } = (
        from nonTracked in existingAfterAndNewlyAdded
        join inTracking in existingAfterInTracking
            on postIdSelector(nonTracked) equals postIdSelector(inTracking) into inTrackings
        from inTracking in inTrackings.DefaultIfEmpty()
        select inTracking ?? nonTracked).ToList().AsReadOnly();
}
