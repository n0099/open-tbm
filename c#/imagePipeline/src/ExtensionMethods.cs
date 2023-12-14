using Point = OpenCvSharp.Point;

namespace tbm.ImagePipeline;

public static class ExtensionMethods
{
    public static (Point TopLeft, Point TopRight, Point BottomLeft, Point BottomRight)
        GetPoints(this RotatedRect rotatedRect)
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
