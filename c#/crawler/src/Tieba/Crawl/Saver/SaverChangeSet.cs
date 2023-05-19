using System.Collections.ObjectModel;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public class SaverChangeSet<T> where T : class, IPost
{
    public ReadOnlyCollection<(T Before, T After)> Existing { get; }
    public ReadOnlyCollection<T> NewlyAdded { get; }
    public ReadOnlyCollection<T> AllAfter { get; }

    public SaverChangeSet(ICollection<T> existingBefore, ICollection<T> existingAfterAndNewlyAdded, Func<T, PostId> postIdSelector)
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
        AllAfter = new(existingAfterAndNewlyAdded.ToList());
    }
}
