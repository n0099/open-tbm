using System.Drawing;
using Emgu.CV;
using Emgu.CV.Structure;

namespace tbm.Crawler.ImagePipeline.Ocr;

public static class MatExtension
{
    /// <summary>
    /// <see>https://stackoverflow.com/questions/22041699/rotate-an-image-without-cropping-in-opencv-in-c/75451191#75451191</see>
    /// </summary>
    public static void Rotate(this Mat src, float degrees)
    {
        degrees = -degrees; // counter-clockwise to clockwise
        var center = new PointF((src.Width - 1) / 2f, (src.Height - 1) / 2f);
        using var rotationMat = new Mat();
        CvInvoke.GetRotationMatrix2D(center, degrees, 1, rotationMat);
        var boundingRect = new RotatedRect(new(), src.Size, degrees).MinAreaRect();
        rotationMat.Set(0, 2, rotationMat.Get<double>(0, 2) + (boundingRect.Width / 2f) - (src.Width / 2f));
        rotationMat.Set(1, 2, rotationMat.Get<double>(1, 2) + (boundingRect.Height / 2f) - (src.Height / 2f));
        CvInvoke.WarpAffine(src, src, rotationMat, boundingRect.Size);
    }

    /// <summary>
    /// <see>https://stackoverflow.com/questions/32255440/how-can-i-get-and-set-pixel-values-of-an-emgucv-mat-image/69537504#69537504</see>
    /// </summary>
    public static unsafe void Set<T>(this Mat mat, int row, int col, T value) =>
        _ = new Span<T>(mat.DataPointer.ToPointer(), mat.Rows * mat.Cols * mat.ElementSize)
        {
            [(row * mat.Cols) + col] = value
        };

    public static unsafe T Get<T>(this Mat mat, int row, int col) =>
        new ReadOnlySpan<T>(mat.DataPointer.ToPointer(), mat.Rows * mat.Cols * mat.ElementSize)
            [(row * mat.Cols) + col];

    public static unsafe ReadOnlySpan<T> Get<T>(this Mat mat, int row, System.Range cols)
    {
        var span = new ReadOnlySpan<T>(mat.DataPointer.ToPointer(), mat.Rows * mat.Cols * mat.ElementSize);
        var (offset, length) = cols.GetOffsetAndLength(span.Length);
        return span.Slice((row * mat.Cols) + offset, length);
    }
}
