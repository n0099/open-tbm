using System.Reflection;

namespace tbm.Shared;

public static partial class ExtensionMethods
{
    /// <see>https://stackoverflow.com/questions/10295028/c-sharp-empty-string-null/10295082#10295082</see>
    public static string? NullIfEmpty(this string? value) => string.IsNullOrEmpty(value) ? null : value;
    public static int? NullIfZero(this int num) => num == 0 ? null : num;
    public static uint? NullIfZero(this uint num) => num == 0 ? null : num;
    public static long? NullIfZero(this long num) => num == 0 ? null : num;
    public static float NanToZero(this float number) => float.IsNaN(number) ? 0 : number;
    public static float? NanToNull(this float number) => float.IsNaN(number) ? null : number;
    public static byte RoundToByte(this double number) => (byte)Math.Round(number);
    public static byte RoundToByte(this float number) => (byte)Math.Round(number);
    public static short RoundToShort(this float number) => (short)Math.Round(number);
    public static ushort RoundToUshort(this float number) => (ushort)Math.Round(number);
    public static ushort RoundToUshort(this double number) => (ushort)Math.Round(number);

    /// <see>https://stackoverflow.com/questions/13158121/how-to-add-a-range-of-items-to-an-ilist/33104162#33104162</see>
    [System.Diagnostics.CodeAnalysis.SuppressMessage("StyleCop.CSharp.DocumentationRules", "SA1618:Generic type parameters should be documented")]
    public static void AddRange<T>(this IList<T> list, IEnumerable<T> items)
    {
        if (list is List<T> asList) asList.AddRange(items);
        else foreach (var item in items) list.Add(item);
    }

    /// <see>https://stackoverflow.com/questions/1474863/addrange-to-a-collection/26360010#26360010</see>
    [System.Diagnostics.CodeAnalysis.SuppressMessage("StyleCop.CSharp.DocumentationRules", "SA1618:Generic type parameters should be documented")]
    public static void AddRange<T>(this ICollection<T> list, IEnumerable<T> items)
    {
        if (list is List<T> asList) asList.AddRange(items);
        else foreach (var item in items) list.Add(item);
    }

    public static IEnumerable<TKey> Keys<TKey, TValue>(this IEnumerable<KeyValuePair<TKey, TValue>> pairs) =>
        pairs.Select(i => i.Key);
    public static IEnumerable<TValue> Values<TKey, TValue>(this IEnumerable<KeyValuePair<TKey, TValue>> pairs) =>
        pairs.Select(i => i.Value);

    public static IEnumerable<KeyValuePair<TKey, TValue>> ExceptByKey<TKey, TValue>(
        this IEnumerable<KeyValuePair<TKey, TValue>> first,
        IEnumerable<TKey> second) =>
        first.ExceptBy(second, pair => pair.Key);
    public static IEnumerable<KeyValuePair<TKey, TValue>> IntersectByKey<TKey, TValue>(
        this IEnumerable<KeyValuePair<TKey, TValue>> first,
        IEnumerable<TKey> second) =>
        first.IntersectBy(second, pair => pair.Key);
}
public static partial class ExtensionMethods
{
    public static void RegisterImplementsOfBaseTypes
        (this ContainerBuilder builder, Assembly assembly, IEnumerable<Type> baseTypes) =>
        builder.RegisterAssemblyTypes(assembly)
            .Where(type => baseTypes.Any(baseType => baseType.IsSubTypeOfRawGeneric(type)))
            .AsSelf();

    /// <see>https://stackoverflow.com/questions/457676/check-if-a-class-is-derived-from-a-generic-class/25937893#25937893</see>
    private static bool IsSubTypeOfRawGeneric(this Type generic, Type toCheck) =>
        generic.IsInterface ? generic.IsImplementerOfRawGeneric(toCheck) : generic.IsSubClassOfRawGeneric(toCheck);

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
        Array.Exists(toCheck.GetInterfaces(),
            type => type.IsGenericType && type.GetGenericTypeDefinition() == generic);
}
