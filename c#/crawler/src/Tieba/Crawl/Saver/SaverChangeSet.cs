namespace tbm.Crawler.Tieba.Crawl.Saver;

public class SaverChangeSet<TPost>(
    IReadOnlyCollection<TPost> existingBefore,
    ICollection<TPost> existingAfterAndNewlyAdded,
    Func<TPost, PostId> postIdSelector)
    where TPost : BasePost
{
    public IReadOnlyCollection<(TPost Before, TPost After)> Existing { get; } = existingBefore
        .OrderBy(postIdSelector)
        .EquiZip(existingAfterAndNewlyAdded
                .IntersectBy(existingBefore.Select(postIdSelector), postIdSelector)
                .OrderBy(postIdSelector),
            (before, after) => (before, after))
        .ToList().AsReadOnly();

    public IReadOnlyCollection<TPost> NewlyAdded { get; } = existingAfterAndNewlyAdded
        .ExceptBy(existingBefore.Select(postIdSelector), postIdSelector)
        .ToList().AsReadOnly();

    public IReadOnlyCollection<TPost> AllAfter { get; } = existingAfterAndNewlyAdded
        .ToList().AsReadOnly();
}
