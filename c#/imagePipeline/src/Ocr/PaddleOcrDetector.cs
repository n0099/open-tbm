namespace tbm.ImagePipeline.Ocr;

public class PaddleOcrDetector(Sdcb.PaddleOCR.PaddleOcrDetector detector)
{
    public delegate PaddleOcrDetector New(Sdcb.PaddleOCR.PaddleOcrDetector detector);

    public IEnumerable<Either<ImageId, IEnumerable<DetectionResult>>> DetectMatrices(
        IReadOnlyDictionary<ImageKey, Mat> matricesKeyByImageKey,
        FailedImageHandler failedImageHandler,
        CancellationToken stoppingToken = default) =>
        failedImageHandler.TrySelect(matricesKeyByImageKey,
            pair => pair.Key.ImageId,
            pair =>
            {
                stoppingToken.ThrowIfCancellationRequested();
                return detector.Run(pair.Value).Select(rect => new DetectionResult(pair.Key, rect));
            });

    public record DetectionResult(ImageKey ImageKey, RotatedRect TextBox);
}
