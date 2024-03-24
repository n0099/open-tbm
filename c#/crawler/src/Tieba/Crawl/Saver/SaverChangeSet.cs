using System.Collections.ObjectModel;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public class SaverChangeSet<TPost> where TPost : class, IPost
{
    public SaverChangeSet(
        ICollection<TPost> existingBefore,
        ICollection<TPost> existingAfterAndNewlyAdded,
        Func<TPost, PostId> postIdSelector)
    {
        var existingAfter = existingAfterAndNewlyAdded
            .IntersectBy(existingBefore.Select(postIdSelector), postIdSelector)
            .OrderBy(postIdSelector).ToList();
        Existing = new(existingBefore
            .OrderBy(postIdSelector)
            .EquiZip(existingAfter, (before, after) => (before, after))
            .ToList());
        NewlyAdded = new(existingAfterAndNewlyAdded
            .ExceptBy(existingBefore.Select(postIdSelector), postIdSelector)
            .ToList());
        AllAfter = new([.. existingAfterAndNewlyAdded]);
    }

    public ReadOnlyCollection<(TPost Before, TPost After)> Existing { get; }
    public ReadOnlyCollection<TPost> NewlyAdded { get; }

    // ReSharper disable once CollectionNeverUpdated.Global
    public ReadOnlyCollection<TPost> AllAfter { get; }
}
