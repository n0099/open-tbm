namespace tbm.Crawler
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
                throw new ArgumentOutOfRangeException(nameof(max), "Max must be > min!");

            var uRange = (ulong)(max - min);
            ulong ulongRand;
            do
            {
                var buf = new byte[8];
                random.NextBytes(buf);
                ulongRand = unchecked((ulong)BitConverter.ToInt64(buf, 0));
            } while (ulongRand > ulong.MaxValue - (((ulong.MaxValue % uRange) + 1) % uRange));

            return (long)(ulongRand % uRange) + min;
        }

        /// <see>https://stackoverflow.com/questions/1651619/optimal-linq-query-to-get-a-random-sub-collection-shuffle/1653204#1653204</see>
        public static IEnumerable<T> Shuffle<T>(this IEnumerable<T> source) => source.Shuffle(new());

        private static IEnumerable<T> Shuffle<T>(this IEnumerable<T> source, Random rng)
        {
            if (source == null) throw new ArgumentNullException(nameof(source));
            if (rng == null) throw new ArgumentNullException(nameof(rng));

            return source.ShuffleIterator(rng);
        }

        private static IEnumerable<T> ShuffleIterator<T>(this IEnumerable<T> source, Random rng)
        {
            var buffer = source.ToList();
            for (var i = 0; i < buffer.Count; i++)
            {
                var j = rng.Next(i, buffer.Count);
                yield return buffer[j];

                buffer[j] = buffer[i];
            }
        }

        public static string GetStrProp(this JsonElement el, string propName) => el.GetProperty(propName).GetString() ?? "";

        /// <see>https://stackoverflow.com/questions/10295028/c-sharp-empty-string-null/10295082#10295082</see>
        public static string? NullIfWhiteSpace(this string? value) => string.IsNullOrWhiteSpace(value) ? null : value;

        /// <see>https://stackoverflow.com/questions/101265/why-is-there-no-foreach-extension-method-on-ienumerable/101278#101278</see>
        public static void ForEach<T>(this IEnumerable<T> source, Action<T> action)
        {
            foreach (var element in source) action(element);
        }

        /// <see>https://stackoverflow.com/questions/9464112/c-sharp-get-value-subset-from-dictionary-by-keylist/9464468#9464468</see>
        public static IEnumerable<TValue> GetValuesByKeys<TKey, TValue>(this IDictionary<TKey, TValue> dict, IEnumerable<TKey> keys) =>
            keys.Where(dict.ContainsKey).Select(x => dict[x]);

        /// <see>https://stackoverflow.com/questions/457676/check-if-a-class-is-derived-from-a-generic-class/457708#457708</see>
        private static bool IsSubClassOfRawGeneric(this Type generic, Type? toCheck)
        {
            while (toCheck != null && toCheck != typeof(object))
            {
                var cur = toCheck.IsGenericType ? toCheck.GetGenericTypeDefinition() : toCheck;
                if (generic == cur) return true;
                toCheck = toCheck.BaseType;
            }

            return false;
        }

        /// <see>https://stackoverflow.com/questions/4963160/how-to-determine-if-a-type-implements-an-interface-with-c-sharp-reflection/4963190#4963190</see>
        private static bool IsImplementerOfRawGeneric(this Type generic, Type toCheck) =>
            toCheck.GetInterfaces().Any(i => i.IsGenericType && i.GetGenericTypeDefinition() == generic);

        /// <see>https://stackoverflow.com/questions/457676/check-if-a-class-is-derived-from-a-generic-class/25937893#25937893</see>
        public static bool IsSubTypeOfRawGeneric(this Type generic, Type toCheck) =>
            generic.IsInterface ? generic.IsImplementerOfRawGeneric(toCheck) : generic.IsSubClassOfRawGeneric(toCheck);

        public static List<T> ToCloned<T>(this IEnumerable<T> list) where T : ICloneable => list.Select(i => (T)i.Clone()).ToList();

        public static Exception ExtractInnerExceptionsData(this Exception e)
        {
            var inner = e.InnerException;
            do
            { // recursive merge all data of exceptions into e.Data
                if (inner == null) continue;
                foreach (var dataKey in inner.Data.Keys)
                    e.Data[dataKey] = inner.Data[dataKey];
                inner = inner.InnerException;
            } while (inner != null);

            return e;
        }

        /// <see>https://stackoverflow.com/questions/9314172/getting-all-messages-from-innerexceptions/9314368#9314368</see>
        public static IEnumerable<Exception> GetInnerExceptions(this Exception ex)
        {
            if (ex == null) throw new ArgumentNullException(nameof(ex));

            var inner = ex;
            do
            {
                yield return inner;
                inner = inner.InnerException;
            } while (inner != null);
        }

        public static void SetIfNotNull<T1, T2>(this IDictionary<T1, T2> dict, T1 key, T2? value) where T2 : struct
        {
            if (value != null) dict[key] = value.Value;
        }

        public static void SetIfNotNull<T1, T2>(this IDictionary<T1, T2> dict, T1 key, T2? value)
        {
            if (value != null) dict[key] = value;
        }

        public static void AddIfNotNull<T>(this IList<T> list, T? item)
        {
            if (item != null) list.Add(item);
        }

        public static int? NullIfZero(this int num) => num == 0 ? null : num;
        public static uint? NullIfZero(this uint num) => num == 0 ? null : num;
        public static long? NullIfZero(this long num) => num == 0 ? null : num;
    }
}
