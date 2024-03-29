using Sdcb.PaddleOCR;

namespace tbm.ImagePipeline.Ocr;

public class PaddleOcrRecognizer(PaddleOcrAll ocr)
{
    public delegate PaddleOcrRecognizer New(PaddleOcrAll ocr);

    public IEnumerable<Either<ImageId, IEnumerable<PaddleOcrRecognitionResult>>> RecognizeMatrices(
        IReadOnlyDictionary<ImageKey, Mat> matricesKeyByImageKey,
        FailedImageHandler failedImageHandler,
        CancellationToken stoppingToken = default) =>
        failedImageHandler.TrySelect(matricesKeyByImageKey,
            pair => pair.Key.ImageId,
            pair =>
            {
                stoppingToken.ThrowIfCancellationRequested();
                return ocr.Run(pair.Value).Regions.Select(region => new PaddleOcrRecognitionResult(
                    pair.Key, region.Rect, region.Text,
                    (region.Score * 100).NanToZero().RoundToByte(),
                    ocr.Recognizer.Model.Version));
            });
}
