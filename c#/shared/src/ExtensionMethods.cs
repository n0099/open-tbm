using Microsoft.EntityFrameworkCore;

namespace tbm.Shared;

public static class ExtensionMethods
{
    /// <see>https://stackoverflow.com/questions/10295028/c-sharp-empty-string-null/10295082#10295082</see>
    public static string? NullIfEmpty(this string? value) => string.IsNullOrEmpty(value) ? null : value;
    public static int? NullIfZero(this int num) => num == 0 ? null : num;
    public static uint? NullIfZero(this uint num) => num == 0 ? null : num;
    public static long? NullIfZero(this long num) => num == 0 ? null : num;
    public static float NanToZero(this float number) => float.IsNaN(number) ? 0 : number;
    public static float? NanToNull(this float number) => float.IsNaN(number) ? null : number;
    public static short RoundToShort(this float number) => (short)Math.Round(number);
    public static short RoundToShort(this double number) => (short)Math.Round(number);
    public static ushort RoundToUshort(this float number) => (ushort)Math.Round(number);
    public static ushort RoundToUshort(this double number) => (ushort)Math.Round(number);
    public static IQueryable<T> ForUpdate<T>(this IQueryable<T> query) => query.TagWith("ForUpdate");
}
