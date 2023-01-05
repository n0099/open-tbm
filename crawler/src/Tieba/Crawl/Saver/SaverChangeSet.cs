using System.Collections.ObjectModel;

namespace tbm.Crawler.Tieba.Crawl.Saver
{
    public class SaverChangeSet<T> where T : class, IPost
    {
        public ReadOnlyCollection<(T Before, T After)> Existing { get; }
        public ReadOnlyCollection<T> NewlyAdded { get; }
        public ReadOnlyCollection<T> AllAfter { get; }

        public SaverChangeSet(ICollection<T> existingBefore, ICollection<T> existingAfterTimestampingAndNewlyAdded, Func<T, PostId> postIdSelector)
        {
            var existingAfter = existingAfterTimestampingAndNewlyAdded
                .IntersectBy(existingBefore.Select(postIdSelector), postIdSelector)
                .OrderBy(postIdSelector).ToList();
            if (existingAfter.Count != existingBefore.Count) throw new(
                "Length of existingAfter is not match with existingBefore.");
            Existing = new(existingBefore
                .OrderBy(postIdSelector)
                .Zip(existingAfter, (before, after) => (before, after)).ToList());
            NewlyAdded = new(existingAfterTimestampingAndNewlyAdded
                .ExceptBy(existingBefore.Select(postIdSelector), postIdSelector).ToList());
            AllAfter = new(existingAfterTimestampingAndNewlyAdded.ToList());
        }
    }
}
