namespace tbm.Shared;

public static class ExtensionMethods
{
    /// <see>https://stackoverflow.com/questions/10295028/c-sharp-empty-string-null/10295082#10295082</see>
    public static string? NullIfEmpty(this string? value) => string.IsNullOrEmpty(value) ? null : value;
    public static int? NullIfZero(this int num) => num == 0 ? null : num;
    public static uint? NullIfZero(this uint num) => num == 0 ? null : num;
    public static long? NullIfZero(this long num) => num == 0 ? null : num;
    public static float NanToZero(this float number) => float.IsNaN(number) ? 0 : number;
    public static ushort RoundToUshort(this float number) => (ushort)Math.Round(number, 0);
    public static ushort RoundToUshort(this double number) => (ushort)Math.Round(number, 0);
}
