using Point = OpenCvSharp.Point;

namespace tbm.ImagePipeline;

public static class ExtensionMethods
{
    /// <see>https://stackoverflow.com/questions/101265/why-is-there-no-foreach-extension-method-on-ienumerable/101278#101278</see>
    public static void ForEach<T>(this IEnumerable<T> source, Action<T> action)
    {
        foreach (var element in source) action(element);
    }

    public static (Point TopLeft, Point TopRight, Point BottomLeft, Point BottomRight) GetPoints(this RotatedRect rotatedRect)
    {
        var points = rotatedRect.Points();
        var topPoints = points.OrderBy(p => p.Y).Take(2).ToList();
        var bottomPoints = points.OrderByDescending(p => p.Y).Take(2).ToList();
        return ( // https://github.com/shimat/opencvsharp/issues/1541
            topPoints.MinBy(p => p.X).ToPoint(),
            topPoints.MaxBy(p => p.X).ToPoint(),
            bottomPoints.MinBy(p => p.X).ToPoint(),
            bottomPoints.MaxBy(p => p.X).ToPoint());
    }
}
