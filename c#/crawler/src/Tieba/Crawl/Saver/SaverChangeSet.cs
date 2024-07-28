namespace tbm.Crawler.Tieba.Crawl.Saver;

public class SaverChangeSet<TPostEntity, TParsedPost>(
    Func<TPostEntity, PostId> postIdSelector,
    Func<TParsedPost, PostId> parsedPostIdSelector,
    ICollection<TParsedPost> parsed,
    IReadOnlyCollection<TPostEntity> existingBefore,
    IReadOnlyCollection<TPostEntity> existingAfter)
    where TPostEntity : IPost
    where TParsedPost : TPostEntity, IPost.IParsed
{
    public IReadOnlyCollection<(TPostEntity Before, TPostEntity After)> ExistingInTracking { get; } = existingBefore
        .OrderBy(postIdSelector)
        .EquiZip(existingAfter
                .IntersectBy(existingBefore.Select(postIdSelector), postIdSelector)
                .OrderBy(postIdSelector),
            (before, after) => (before, after))
        .ToList().AsReadOnly();

    public IReadOnlyCollection<TParsedPost> NewlyAdded { get; } = parsed
        .ExceptBy(existingBefore.Select(postIdSelector), parsedPostIdSelector)
        .ToList().AsReadOnly();

    // https://stackoverflow.com/questions/3404975/left-outer-join-in-linq/23558389#23558389
    public IReadOnlyCollection<TPostEntity> AllAfterInTrackingOrParsed { get; } = (
        from notTracked in parsed
        join inTracking in existingAfter
            on postIdSelector(notTracked) equals postIdSelector(inTracking) into inTrackings
        from inTracking in inTrackings.DefaultIfEmpty()
        select inTracking ?? notTracked).ToList().AsReadOnly();

    public IReadOnlyCollection<TParsedPost> AllParsed { get; } = parsed.ToList().AsReadOnly();
}
