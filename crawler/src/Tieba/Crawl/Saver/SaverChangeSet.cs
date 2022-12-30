using System.Collections.ObjectModel;

namespace tbm.Crawler.Tieba.Crawl.Saver
{
    public class SaverChangeSet<T> where T : class, IPost
    {
        public readonly ReadOnlyCollection<(T Before, T After)> Existing;
        public readonly ReadOnlyCollection<T> NewlyAdded;
        public readonly ReadOnlyCollection<T> AllAfter;

        public SaverChangeSet(ICollection<T> existingBefore, ICollection<T> existingAfterAndNewly, Func<T, PostId> postIdSelector)
        {
            var existingAfter = existingAfterAndNewly
                .IntersectBy(existingBefore.Select(postIdSelector), postIdSelector)
                .OrderBy(postIdSelector).ToList();
            if (existingAfter.Count != existingBefore.Count) throw new(
                "Length of existingAfter is not match with existingBefore.");
            Existing = new(existingBefore
                .OrderBy(postIdSelector)
                .Zip(existingAfter, (before, after) => (before, after)).ToList());
            NewlyAdded = new(existingAfterAndNewly
                .ExceptBy(existingBefore.Select(postIdSelector), postIdSelector).ToList());
            AllAfter = new(existingAfterAndNewly.ToList());
        }
    }
}
