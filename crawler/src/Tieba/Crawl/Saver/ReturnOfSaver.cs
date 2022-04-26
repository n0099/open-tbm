using System.Collections.ObjectModel;

namespace tbm.Crawler
{
    public class ReturnOfSaver<T> where T : IPost
    {
        public readonly ReadOnlyCollection<(T Before, T After)> Existing;
        public readonly ReadOnlyCollection<T> NewlyAdded;
        public readonly ReadOnlyCollection<T> AllModified;

        public ReturnOfSaver(IList<T> existingBefore, ICollection<T> existingAfterAndNewly, Func<T, PostId> postIdSelector)
        {
            var existingAfter = existingAfterAndNewly.IntersectBy(existingBefore.Select(postIdSelector), postIdSelector).OrderBy(postIdSelector).ToList();
            var beforeAndAfter = existingBefore.OrderBy(postIdSelector).Zip(existingAfter, (before, after) => (before, after));
            if (existingAfter.Count != existingBefore.Count) throw new Exception("Length of existingAfter is not match with existingBefore");
            Existing = new ReadOnlyCollection<(T Before, T After)>(beforeAndAfter.ToList());
            var newly = existingAfterAndNewly.ExceptBy(existingBefore.Select(postIdSelector), postIdSelector).ToList();
            NewlyAdded = new ReadOnlyCollection<T>(newly);
            AllModified = new ReadOnlyCollection<T>(existingAfterAndNewly.ToList());
        }
    }
}
