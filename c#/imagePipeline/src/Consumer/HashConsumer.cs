using OpenCvSharp.ImgHash;
using Size = OpenCvSharp.Size;

namespace tbm.ImagePipeline.Consumer;

public class HashConsumer : MatrixConsumer, IDisposable
{
    private readonly Dictionary<ImgHashBase, Action<ImageHash, byte[]>> _imageHashSettersKeyByAlgorithm;

    public HashConsumer() => _imageHashSettersKeyByAlgorithm = new()
    {
        {PHash.Create(), (image, bytes) => image.PHash = BitConverter.ToUInt64(bytes)},
        {AverageHash.Create(), (image, bytes) => image.AverageHash = BitConverter.ToUInt64(bytes)},
        {BlockMeanHash.Create(), (image, bytes) => image.BlockMeanHash = bytes},
        {MarrHildrethHash.Create(), (image, bytes) => image.MarrHildrethHash = bytes}
    };

    public void Dispose() => _imageHashSettersKeyByAlgorithm.Keys.ForEach(hash => hash.Dispose());

    protected override void ConsumeInternal(
        ImagePipelineDbContext db,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default)
    {
        var hashesKeyByImageKey = imageKeysWithMatrix.ToDictionary(
            imageKeyWithMatrix => imageKeyWithMatrix,
            imageKeyWithMatrix => new ImageHash
            {
                ImageId = imageKeyWithMatrix.ImageId,
                FrameIndex = imageKeyWithMatrix.FrameIndex,
                BlockMeanHash = Array.Empty<byte>(),
                MarrHildrethHash = Array.Empty<byte>(),
                ThumbHash = Array.Empty<byte>()
            });
        imageKeysWithMatrix.ForEach(imageKeyWithMatrix =>
        {
            stoppingToken.ThrowIfCancellationRequested();
            var mat = imageKeyWithMatrix.Matrix;
            if (mat.Width > 100 || mat.Height > 100)
            { // not preserve the original aspect ratio, https://stackoverflow.com/questions/44650888/resize-an-image-without-distortion-opencv
                using var thumbnail = mat.Resize(new(100, 100), interpolation: InterpolationFlags.Area);
                hashesKeyByImageKey[imageKeyWithMatrix].ThumbHash = GetThumbHash(thumbnail);
            }
            else hashesKeyByImageKey[imageKeyWithMatrix].ThumbHash = GetThumbHash(mat);

            static byte[] GetThumbHash(Mat mat)
            {
                using var rgbaMat = new Mat(new Size(mat.Width, mat.Height), MatType.CV_8UC4);
                // https://stackoverflow.com/questions/67550415/in-place-rgb-bgr-color-conversion-is-slower-in-opencv
                Cv2.CvtColor(mat, rgbaMat, ColorConversionCodes.BGRA2RGBA);
                return rgbaMat.GetArray(out Vec4b[] pixels)
                    ? ThumbHash.ThumbHash.RgbaToThumbHash(mat.Width, mat.Height,
                        pixels.Select(vec => new[] {vec.Item0, vec.Item1, vec.Item2, vec.Item3})
                            .SelectMany(i => i).ToArray())
                    : throw new("Failed to convert matrix into byte array.");
            }
        });
        _imageHashSettersKeyByAlgorithm.ForEach(hashPair => imageKeysWithMatrix.ForEach(imageKeyWithMatrix =>
        {
            stoppingToken.ThrowIfCancellationRequested();
            using var mat = new Mat();
            hashPair.Key.Compute(imageKeyWithMatrix.Matrix, mat);
            if (mat.GetArray(out byte[] bytes))
                hashPair.Value(hashesKeyByImageKey[imageKeyWithMatrix], bytes);
            else throw new("Failed to convert matrix into byte array.");
        }));
        db.ImageHashes.AddRange(hashesKeyByImageKey.Values);
    }
}
