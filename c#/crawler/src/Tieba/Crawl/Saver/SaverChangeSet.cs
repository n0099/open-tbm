using System.Collections.ObjectModel;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public class SaverChangeSet<TPost> where TPost : class, IPost
{
    public SaverChangeSet(
        IReadOnlyCollection<TPost> existingBefore,
        ICollection<TPost> existingAfterAndNewlyAdded,
        Func<TPost, PostId> postIdSelector)
    {
        Existing = existingBefore
            .OrderBy(postIdSelector)
            .EquiZip(existingAfterAndNewlyAdded
                .IntersectBy(existingBefore.Select(postIdSelector), postIdSelector)
                .OrderBy(postIdSelector),
                (before, after) => (before, after))
            .ToList().AsReadOnly();
        NewlyAdded = existingAfterAndNewlyAdded
            .ExceptBy(existingBefore.Select(postIdSelector), postIdSelector)
            .ToList().AsReadOnly();
        AllAfter = existingAfterAndNewlyAdded.ToList().AsReadOnly();
    }

    public IReadOnlyCollection<(TPost Before, TPost After)> Existing { get; }
    public IReadOnlyCollection<TPost> NewlyAdded { get; }
    public IReadOnlyCollection<TPost> AllAfter { get; }
}
