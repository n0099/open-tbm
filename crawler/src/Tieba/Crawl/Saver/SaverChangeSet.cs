using System.Collections.ObjectModel;

namespace tbm.Crawler
{
    public class SaverChangeSet<T> where T : class, IPost
    {
        public readonly ReadOnlyCollection<(T Before, T After)> Existing;
        public readonly ReadOnlyCollection<T> NewlyAdded;

        public SaverChangeSet(ICollection<T> existingBefore, ICollection<T> existingAfterAndNewly, Func<T, PostId> postIdSelector)
        {
            var existingAfter = existingAfterAndNewly.IntersectBy(existingBefore.Select(postIdSelector), postIdSelector).OrderBy(postIdSelector).ToList();
            var beforeAndAfter = existingBefore.OrderBy(postIdSelector).Zip(existingAfter, (before, after) => (before, after));
            if (existingAfter.Count != existingBefore.Count) throw new("Length of existingAfter is not match with existingBefore");
            Existing = new(beforeAndAfter.ToList());
            var newly = existingAfterAndNewly.ExceptBy(existingBefore.Select(postIdSelector), postIdSelector).ToList();
            NewlyAdded = new(newly);
        }
    }
}
