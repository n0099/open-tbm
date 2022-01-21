using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;

namespace tbm
{
    public static class ExtensionMethods
    {
        /// <summary>
        /// Returns a random long from min (inclusive) to max (exclusive)
        /// </summary>
        /// <param name="random">The given random instance</param>
        /// <param name="min">The inclusive minimum bound</param>
        /// <param name="max">The exclusive maximum bound.  Must be greater than min</param>
        /// <see>https://stackoverflow.com/questions/6651554/random-number-in-long-range-is-this-the-way/13095144#13095144</see>
        public static long NextLong(this Random random, long min, long max)
        {
            if (max <= min)
                throw new ArgumentOutOfRangeException(nameof(max), "max must be > min!");

            ulong uRange = (ulong)(max - min);
            ulong ulongRand;
            do
            {
                byte[] buf = new byte[8];
                random.NextBytes(buf);
                ulongRand = unchecked((ulong)BitConverter.ToInt64(buf, 0));
            } while (ulongRand > ulong.MaxValue - ((ulong.MaxValue % uRange) + 1) % uRange);

            return (long)(ulongRand % uRange) + min;
        }

        /// <see>https://stackoverflow.com/questions/1651619/optimal-linq-query-to-get-a-random-sub-collection-shuffle/1653204#1653204</see>
        public static IEnumerable<T> Shuffle<T>(this IEnumerable<T> source)
        {
            return source.Shuffle(new Random());
        }

        public static IEnumerable<T> Shuffle<T>(this IEnumerable<T> source, Random rng)
        {
            if (source == null) throw new ArgumentNullException(nameof(source));
            if (rng == null) throw new ArgumentNullException(nameof(rng));

            return source.ShuffleIterator(rng);
        }

        private static IEnumerable<T> ShuffleIterator<T>(this IEnumerable<T> source, Random rng)
        {
            var buffer = source.ToList();
            for (int i = 0; i < buffer.Count; i++)
            {
                int j = rng.Next(i, buffer.Count);
                yield return buffer[j];

                buffer[j] = buffer[i];
            }
        }

        public static string GetStrProp(this JsonElement el, string propName) => el.GetProperty(propName).GetString() ?? "";

        /// <see>https://stackoverflow.com/questions/10295028/c-sharp-empty-string-null/10295082#10295082</see>
        public static string? NullIfWhiteSpace(this string? value) => string.IsNullOrWhiteSpace(value) ? null : value;
    }
}
