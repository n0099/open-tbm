namespace tbm.ImagePipeline.Consumer;

public class QrCodeConsumer : MatrixConsumer, IDisposable
{
    private readonly ExceptionHandler _exceptionHandler;
    private readonly WeChatQRCode _qrCode;

    public QrCodeConsumer(IConfiguration config, ExceptionHandler exceptionHandler)
    {
        _exceptionHandler = exceptionHandler;
        var modelsPath = config.GetSection("QrCodeConsumer")
            .GetValue("ModelPath", "./OpenCvWechatModels") ?? "./OpenCvWechatModels";
        _qrCode = WeChatQRCode.Create(
            $"{modelsPath}/detect.prototxt",
            $"{modelsPath}/detect.caffemodel",
            $"{modelsPath}/sr.prototxt",
            $"{modelsPath}/sr.caffemodel");
    }

    public void Dispose() => _qrCode.Dispose();

    protected override IEnumerable<ImageId> ConsumeInternal(
        ImagePipelineDbContext db,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default)
    {
        var imageQrCodes = imageKeysWithMatrix.Select(
            _exceptionHandler.Try<ImageKeyWithMatrix, IEnumerable<ImageQrCode>>(
                imageKeyWithMatrix => imageKeyWithMatrix.ImageId,
                imageKeyWithMatrix =>
                {
                    _qrCode.DetectAndDecode(imageKeyWithMatrix.Matrix, out var boxes, out var results);
                    return boxes.EquiZip(results).Select(t =>
                    {
                        var (boxMat, result) = t;
                        if (boxMat.Width != 2 || boxMat.Height != 4)
                            throw new($"Unexpected matrix \"{boxMat}\" returned by WeChatQRCode.DetectAndDecode().");
                        if (!boxMat.GetArray(out float[] boxPoints))
                            throw new("Failed to convert matrix into byte array.");
                        var points = boxPoints.Chunk(2).ToList();
                        return new ImageQrCode
                        {
                            ImageId = imageKeyWithMatrix.ImageId,
                            FrameIndex = imageKeyWithMatrix.FrameIndex,
                            Point1X = points[0][0].RoundToShort(),
                            Point1Y = points[0][1].RoundToShort(),
                            Point2X = points[1][0].RoundToShort(),
                            Point2Y = points[1][1].RoundToShort(),
                            Point3X = points[2][0].RoundToShort(),
                            Point3Y = points[2][1].RoundToShort(),
                            Point4X = points[3][0].RoundToShort(),
                            Point4Y = points[3][1].RoundToShort(),
                            Text = result
                        };
                    });
                }))
            .ToList();
        db.ImageQrCodes.AddRange(imageQrCodes.Lefts().SelectMany(i => i));
        return imageQrCodes.Rights();
    }
}
