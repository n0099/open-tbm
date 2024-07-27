namespace tbm.Crawler.Tieba.Crawl.Saver;

public class SaverChangeSet<TPost>(
    Func<TPost, PostId> postIdSelector,
    ICollection<TPost> parsed,
    IReadOnlyCollection<TPost> existingBefore,
    IReadOnlyCollection<TPost> existingAfter)
    where TPost : BasePost
{
    public IReadOnlyCollection<(TPost Before, TPost After)> Existing { get; } = existingBefore
        .OrderBy(postIdSelector)
        .EquiZip(existingAfter
                .IntersectBy(existingBefore.Select(postIdSelector), postIdSelector)
                .OrderBy(postIdSelector),
            (before, after) => (before, after))
        .ToList().AsReadOnly();

    public IReadOnlyCollection<TPost> NewlyAdded { get; } = parsed
        .ExceptBy(existingBefore.Select(postIdSelector), postIdSelector)
        .ToList().AsReadOnly();

    // https://stackoverflow.com/questions/3404975/left-outer-join-in-linq/23558389#23558389
    public IReadOnlyCollection<TPost> AllAfter { get; } = (
        from notTracked in parsed
        join inTracking in existingAfter
            on postIdSelector(notTracked) equals postIdSelector(inTracking) into inTrackings
        from inTracking in inTrackings.DefaultIfEmpty()
        select inTracking ?? notTracked).ToList().AsReadOnly();

    public IReadOnlyCollection<TPost> Parsed { get; } = parsed.ToList().AsReadOnly();
}
