using OpenCvSharp;

namespace tbm.Crawler.ImagePipeline.Ocr;

public static class MatExtension
{
    /// <summary>
    /// <see>https://stackoverflow.com/questions/22041699/rotate-an-image-without-cropping-in-opencv-in-c/75451191#75451191</see>
    /// </summary>
    public static void Rotate(this Mat src, float degrees)
    {
        degrees = -degrees; // counter-clockwise to clockwise
        var center = new Point2f((src.Width - 1) / 2f, (src.Height - 1) / 2f);
        using var rotationMat = Cv2.GetRotationMatrix2D(center, degrees, 1);
        var srcSize = src.Size();
        var boundingRect = new RotatedRect(new(), new(srcSize.Width, srcSize.Height), degrees).BoundingRect();
        rotationMat.Set(0, 2, rotationMat.Get<double>(0, 2) + (boundingRect.Width / 2f) - (src.Width / 2f));
        rotationMat.Set(1, 2, rotationMat.Get<double>(1, 2) + (boundingRect.Height / 2f) - (src.Height / 2f));
        Cv2.WarpAffine(src, src, rotationMat, boundingRect.Size);
    }
}
