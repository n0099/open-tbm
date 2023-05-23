using System.Data;
using System.Threading.Channels;
using SixLabors.ImageSharp.Formats.Gif;

namespace tbm.ImagePipeline;

public class ImagePipelineWorker : ErrorableWorker
{
    private readonly ILogger<ImagePipelineWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private readonly ChannelReader<List<ImageWithBytes>> _reader;

    public ImagePipelineWorker
        (ILogger<ImagePipelineWorker> logger, ILifetimeScope scope0, Channel<List<ImageWithBytes>> channel) :
        base(logger) => (_logger, _scope0, _reader) = (logger, scope0, channel);

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await foreach (var imagesWithBytes in _reader.ReadAllAsync(stoppingToken))
        {
            await using var scope1 = _scope0.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()("");
            await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);

            MetadataConsumer.Consume(db, imagesWithBytes, stoppingToken);
            var imageKeysWithMatrix = imagesWithBytes.SelectMany(imageWithBytes =>
            {
                var imageBytes = imageWithBytes.Bytes;
                var imageId = imageWithBytes.Image.ImageId;
                // preserve alpha channel if there's any, so the type of mat might be CV_8UC3 or CV_8UC4
                var mat = Cv2.ImDecode(imageBytes, ImreadModes.Unchanged);
                if (!mat.Empty())
                    return new ImageKeyWithMatrix[] {new(imageId, 0, mat)};
                if (Image.DetectFormat(imageBytes) is GifFormat)
                {
                    var image = Image.Load<Rgb24>(imageBytes);
                    return image.Frames.AsEnumerable().Select((frame, frameIndex) =>
                    {
                        var frameBytes = Span<Rgb24>.Empty;
                        frame.CopyPixelDataTo(frameBytes);
                        var frameImage = Image.LoadPixelData<Rgb24>(frameBytes, frame.Width, frame.Height);
                        var stream = new MemoryStream();
                        frameImage.SaveAsPng(stream);
                        if (stream.TryGetBuffer(out var buffer))
                        {
                            var mat2 = Cv2.ImDecode(buffer, ImreadModes.Unchanged);
                            if (mat2.Empty()) throw new($"Failed to decode frame {frameIndex} of image {imageId}.");
                            return new ImageKeyWithMatrix(imageId, (uint)frameIndex, mat2);
                        }
                        throw new ObjectDisposedException(nameof(stream));
                    });
                }
                throw new NotSupportedException($"Image {imageId} cannot decode by OpenCV and is not GIF format.");
            }).ToList();
            try
            {
                var hashConsumer = scope1.Resolve<HashConsumer>();
                hashConsumer.Consume(db, imageKeysWithMatrix, stoppingToken);
                _ = await db.SaveChangesAsync(stoppingToken);
                await transaction.CommitAsync(stoppingToken);
                await ConsumeOcrConsumerWithAllScrips(scope1, imageKeysWithMatrix, stoppingToken);
            }
            finally
            {
                imageKeysWithMatrix.ForEach(i => i.Matrix.Dispose());
            }
        }
    }

    private static async Task ConsumeOcrConsumerWithAllScrips(
        ILifetimeScope scope,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default)
    {
        foreach (var script in new[] {"zh-Hans", "zh-Hant", "ja", "en"})
        {
            await using var scope1 = scope.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()(script);
            await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);

            var ocrConsumer = scope1.Resolve<OcrConsumer.New>()(script);
            await ocrConsumer.InitializePaddleOcr(stoppingToken);
            ocrConsumer.Consume(db, imageKeysWithMatrix, stoppingToken);

            _ = await db.SaveChangesAsync(stoppingToken);
            await transaction.CommitAsync(stoppingToken);
        }
    }
}
