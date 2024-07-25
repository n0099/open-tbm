using System.Collections.ObjectModel;
using OpenCvSharp.ImgHash;
using Size = OpenCvSharp.Size;

namespace tbm.ImagePipeline.Consumer;

public sealed class HashConsumer : MatrixConsumer, IDisposable
{
    private readonly FailedImageHandler _failedImageHandler;
    private readonly ReadOnlyDictionary<ImgHashBase, Action<ImageHash, byte[]>> _imageHashSettersKeyByAlgorithm;

    [SuppressMessage("Correctness", "SS004:Implement Equals() and GetHashcode() methods for a type used in a collection.")]
    public HashConsumer(FailedImageHandler failedImageHandler)
    {
        _failedImageHandler = failedImageHandler;
        _imageHashSettersKeyByAlgorithm = new Dictionary<ImgHashBase, Action<ImageHash, byte[]>>
        {
            {PHash.Create(), (image, bytes) => image.PHash = bytes},
            {AverageHash.Create(), (image, bytes) => image.AverageHash = bytes},
            {BlockMeanHash.Create(), (image, bytes) => image.BlockMeanHash = bytes},
            {MarrHildrethHash.Create(), (image, bytes) => image.MarrHildrethHash = bytes}
        }.AsReadOnly();
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
                PHash = [],
                AverageHash = [],
                BlockMeanHash = [],
                MarrHildrethHash = [],
                ThumbHash = []
            });

        var thumbHashEithers = _failedImageHandler
            .TrySelect(imageKeysWithMatrix,
                imageKeyWithMatrix => imageKeyWithMatrix.ImageId,
                GetThumbHashForImage(stoppingToken))
            .ToList();
        thumbHashEithers.Rights().ForEach(pair => hashesKeyByImageKey[pair.Key].ThumbHash = pair.Value);

        var hashFailedImagesId = _imageHashSettersKeyByAlgorithm.SelectMany(hashPair =>
        {
            var hashEithers = _failedImageHandler
                .TrySelect(imageKeysWithMatrix,
                    imageKeyWithMatrix => imageKeyWithMatrix.ImageId,
                    i => GetImageHash(i, hashPair.Key, stoppingToken))
                .ToList();
            hashEithers.Rights().ForEach(pair => hashPair.Value(hashesKeyByImageKey[pair.Key], pair.Value));
            return hashEithers.Lefts();
        });

        var failedImagesId = thumbHashEithers.Lefts().Concat(hashFailedImagesId).ToHashSet();
        db.ImageHashes.AddRange(hashesKeyByImageKey
            .IntersectBy( // only insert fully succeeded images id, i.e. all frames and all hashes are calculated
                hashesKeyByImageKey.Keys.Select(i => i.ImageId).Except(failedImagesId),
                pair => pair.Key.ImageId)
            .Values());
        return failedImagesId;
    }

    private static Func<ImageKeyWithMatrix, KeyValuePair<ImageKeyWithMatrix, byte[]>> GetThumbHashForImage
        (CancellationToken stoppingToken = default) => imageKeyWithMatrix =>
    {
        stoppingToken.ThrowIfCancellationRequested();
        var mat = imageKeyWithMatrix.Matrix;
        if (mat is {Width: <= 100, Height: <= 100})
            return new(imageKeyWithMatrix, GetThumbHashForMatrix(mat));

        // not preserve the original aspect ratio
        // https://stackoverflow.com/questions/44650888/resize-an-image-without-distortion-opencv
        using var thumbnail = mat.Resize(new(100, 100), interpolation: InterpolationFlags.Area);
        return new(imageKeyWithMatrix, GetThumbHashForMatrix(thumbnail));

        static byte[] GetThumbHashForMatrix(Mat mat)
        {
            using var rgbaMat = new Mat(new Size(mat.Width, mat.Height), MatType.CV_8UC4);

            // https://stackoverflow.com/questions/67550415/in-place-rgb-bgr-color-conversion-is-slower-in-opencv
            Cv2.CvtColor(mat, rgbaMat, ColorConversionCodes.BGRA2RGBA);
            return rgbaMat.GetArray(out Vec4b[] pixels)
                ? ThumbHashes.Utilities.RgbaToThumbHash(mat.Width, mat.Height, pixels
                    .Select(vec => new[] {vec.Item0, vec.Item1, vec.Item2, vec.Item3})
                    .Flatten2().ToArray())
                : throw new InvalidOperationException("Failed to convert matrix into byte array.");
        }
    };

    private static KeyValuePair<ImageKeyWithMatrix, byte[]> GetImageHash
        (ImageKeyWithMatrix imageKeyWithMatrix, ImgHashBase hashAlgorithm, CancellationToken stoppingToken = default)
    {
        stoppingToken.ThrowIfCancellationRequested();
        using var mat = new Mat();
        hashAlgorithm.Compute(imageKeyWithMatrix.Matrix, mat);
        return mat.GetArray(out byte[] bytes)
            ? new(imageKeyWithMatrix, bytes)
            : throw new InvalidOperationException("Failed to convert matrix into byte array.");
    }
}
