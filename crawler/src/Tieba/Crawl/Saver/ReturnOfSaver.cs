using System.Collections.ObjectModel;

namespace tbm.Crawler
{
    public class ReturnOfSaver<T> where T : IPost
    {
        public readonly (ReadOnlyCollection<T> Before, ReadOnlyCollection<T> After) Existing;
        public readonly ReadOnlyCollection<T> NewlyAdded;
        public readonly ReadOnlyCollection<T> AllModified;

        public ReturnOfSaver(IList<T> existingBefore, ICollection<T> existingAfterAndNewly, Func<T, PostId> postIdSelector)
        {
            Existing.Before = new ReadOnlyCollection<T>(existingBefore);
            var after = existingAfterAndNewly.IntersectBy(existingBefore.Select(postIdSelector), postIdSelector).ToList();
            Existing.After = new ReadOnlyCollection<T>(after);
            var newly = existingAfterAndNewly.ExceptBy(existingBefore.Select(postIdSelector), postIdSelector).ToList();
            NewlyAdded = new ReadOnlyCollection<T>(newly);
            AllModified = new ReadOnlyCollection<T>(existingAfterAndNewly.ToList());
        }
    }
}
