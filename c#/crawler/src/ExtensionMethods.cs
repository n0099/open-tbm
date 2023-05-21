namespace tbm.Crawler;

public static class ExtensionMethods
{
    /// <summary>
    ///     Returns a random long from min (inclusive) to max (exclusive)
    /// </summary>
    /// <param name="random">The given random instance</param>
    /// <param name="min">The inclusive minimum bound</param>
    /// <param name="max">The exclusive maximum bound.  Must be greater than min</param>
    /// <see>https://stackoverflow.com/questions/6651554/random-number-in-long-range-is-this-the-way/13095144#13095144</see>
    public static long NextLong(this Random random, long min, long max)
    {
        Guard.IsLessThanOrEqualTo(min, max);

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

    public static string GetStrProp(this JsonElement el, string propName) => el.GetProperty(propName).GetString() ?? "";

    /// <see>https://stackoverflow.com/questions/10295028/c-sharp-empty-string-null/10295082#10295082</see>
    public static string? NullIfEmpty(this string? value) => string.IsNullOrEmpty(value) ? null : value;

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
        toCheck.GetInterfaces().Any(type => type.IsGenericType && type.GetGenericTypeDefinition() == generic);

    /// <see>https://stackoverflow.com/questions/457676/check-if-a-class-is-derived-from-a-generic-class/25937893#25937893</see>
    public static bool IsSubTypeOfRawGeneric(this Type generic, Type toCheck) =>
        generic.IsInterface ? generic.IsImplementerOfRawGeneric(toCheck) : generic.IsSubClassOfRawGeneric(toCheck);

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
        Guard.IsNotNull(ex);

        var inner = ex;
        do
        {
            yield return inner;
            inner = inner.InnerException;
        } while (inner != null);
    }

    public static void SetIfNotNull<T1, T2>(this IDictionary<T1, T2> dict, T1 key, T2? value)
    {
        if (value != null) dict[key] = value;
    }

    public static int? NullIfZero(this int num) => num == 0 ? null : num;
    public static uint? NullIfZero(this uint num) => num == 0 ? null : num;
    public static long? NullIfZero(this long num) => num == 0 ? null : num;
}
