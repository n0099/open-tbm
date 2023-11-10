using OpenCvSharp.ImgHash;
using Size = OpenCvSharp.Size;

namespace tbm.ImagePipeline.Consumer;

public class HashConsumer : MatrixConsumer, IDisposable
{
    private readonly FailedImageHandler _failedImageHandler;
    private readonly Dictionary<ImgHashBase, Action<ImageHash, byte[]>> _imageHashSettersKeyByAlgorithm;

    public HashConsumer(FailedImageHandler failedImageHandler)
    {
        _failedImageHandler = failedImageHandler;
        _imageHashSettersKeyByAlgorithm = new()
        {
            {PHash.Create(), (image, bytes) => image.PHash = BitConverter.ToUInt64(bytes)},
            {AverageHash.Create(), (image, bytes) => image.AverageHash = BitConverter.ToUInt64(bytes)},
            {BlockMeanHash.Create(), (image, bytes) => image.BlockMeanHash = bytes},
            {MarrHildrethHash.Create(), (image, bytes) => image.MarrHildrethHash = bytes}
        };
    }

    public void Dispose() => _imageHashSettersKeyByAlgorithm.Keys.ForEach(hash => hash.Dispose());

    protected override IEnumerable<ImageId> ConsumeInternal(
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

        var thumbHashEithers = _failedImageHandler
            .TrySelect(imageKeysWithMatrix,
                imageKeyWithMatrix => imageKeyWithMatrix.ImageId,
                imageKeyWithMatrix => GetThumbHashForImage(imageKeyWithMatrix, stoppingToken))
            .ToList();
        thumbHashEithers.Lefts().ForEach(t => hashesKeyByImageKey[t.Key].ThumbHash = t.Value);

        var hashFailedImagesId = _imageHashSettersKeyByAlgorithm.SelectMany(hashPair =>
        {
            var hashEithers = _failedImageHandler
                .TrySelect(imageKeysWithMatrix,
                    imageKeyWithMatrix => imageKeyWithMatrix.ImageId,
                    imageKeyWithMatrix => GetImageHash(imageKeyWithMatrix, hashPair.Key, stoppingToken))
                .ToList();
            hashEithers.Lefts().ForEach(pair => hashPair.Value(hashesKeyByImageKey[pair.Key], pair.Value));
            return hashEithers.Rights();
        });

        var failedImagesId = thumbHashEithers.Rights().Concat(hashFailedImagesId).ToHashSet();
        db.ImageHashes.AddRange(hashesKeyByImageKey
            .IntersectBy( // only insert fully succeeded images id, i.e. all frames and all hashes are calculated
                hashesKeyByImageKey.Keys.Select(i => i.ImageId).Except(failedImagesId),
                pair => pair.Key.ImageId)
            .Select(pair => pair.Value));
        return failedImagesId;
    }

    private KeyValuePair<ImageKeyWithMatrix, byte[]> GetThumbHashForImage
        (ImageKeyWithMatrix imageKeyWithMatrix, CancellationToken stoppingToken)
    {
        stoppingToken.ThrowIfCancellationRequested();
        var mat = imageKeyWithMatrix.Matrix;
        if (mat.Width > 100 || mat.Height > 100)
        { // not preserve the original aspect ratio, https://stackoverflow.com/questions/44650888/resize-an-image-without-distortion-opencv
            using var thumbnail = mat.Resize(new(100, 100), interpolation: InterpolationFlags.Area);
            return new(imageKeyWithMatrix, GetThumbHashForMatrix(thumbnail));
        }
        return new(imageKeyWithMatrix, GetThumbHashForMatrix(mat));

        static byte[] GetThumbHashForMatrix(Mat mat)
        {
            using var rgbaMat = new Mat(new Size(mat.Width, mat.Height), MatType.CV_8UC4);
            // https://stackoverflow.com/questions/67550415/in-place-rgb-bgr-color-conversion-is-slower-in-opencv
            Cv2.CvtColor(mat, rgbaMat, ColorConversionCodes.BGRA2RGBA);
            return rgbaMat.GetArray(out Vec4b[] pixels)
                ? ThumbHashes.Utilities.RgbaToThumbHash(mat.Width, mat.Height, pixels
                    .Select(vec => new[] {vec.Item0, vec.Item1, vec.Item2, vec.Item3})
                    .SelectMany(i => i).ToArray())
                : throw new("Failed to convert matrix into byte array.");
        }
    }

    private KeyValuePair<ImageKeyWithMatrix, byte[]> GetImageHash
        (ImageKeyWithMatrix imageKeyWithMatrix, ImgHashBase hashAlgorithm, CancellationToken stoppingToken)
    {
        stoppingToken.ThrowIfCancellationRequested();
        using var mat = new Mat();
        hashAlgorithm.Compute(imageKeyWithMatrix.Matrix, mat);
        return mat.GetArray(out byte[] bytes)
            ? new(imageKeyWithMatrix, bytes)
            : throw new("Failed to convert matrix into byte array.");
    }
}
