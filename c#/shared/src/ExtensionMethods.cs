namespace tbm.Shared;

public static class ExtensionMethods
{
    public static float NanToZero(this float number) => float.IsNaN(number) ? 0 : number;
    public static ushort RoundToUshort(this float number) => (ushort)Math.Round(number, 0);
    public static ushort RoundToUshort(this double number) => (ushort)Math.Round(number, 0);
}
