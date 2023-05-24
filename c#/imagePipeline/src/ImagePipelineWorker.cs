using System.Data;
using System.Data.Common;
using System.Diagnostics;
using System.Threading.Channels;
using Microsoft.EntityFrameworkCore.Storage;
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

            var metadataConsumer = scope1.Resolve<MetadataConsumer>();
            metadataConsumer.Consume(db, imagesWithBytes, stoppingToken);
            var imageKeysWithMatrix = imagesWithBytes
                .SelectMany(i => DecodeImageOrFramesBytes(i, stoppingToken)).ToList();
            try
            {
                var hashConsumer = scope1.Resolve<HashConsumer>();
                hashConsumer.Consume(db, imageKeysWithMatrix, stoppingToken);
                _ = await db.SaveChangesAsync(stoppingToken);
                await ConsumeOcrConsumerWithAllScrips(scope1, db.Database.GetDbConnection(),
                    transaction.GetDbTransaction(), imageKeysWithMatrix, stoppingToken);
                await transaction.CommitAsync(stoppingToken);
            }
            finally
            {
                imageKeysWithMatrix.ForEach(i => i.Matrix.Dispose());
            }
        }
    }

    private IEnumerable<ImageKeyWithMatrix> DecodeImageOrFramesBytes
        (ImageWithBytes imageWithBytes, CancellationToken stoppingToken = default)
    {
        var imageBytes = imageWithBytes.Bytes;
        var imageId = imageWithBytes.Image.ImageId;
        // preserve alpha channel if there's any, so the type of mat might be CV_8UC3 or CV_8UC4
        var imageMat = Cv2.ImDecode(imageBytes, ImreadModes.Unchanged);
        if (!imageMat.Empty())
            return new ImageKeyWithMatrix[] {new(imageId, 0, imageMat)};
        try
        {
            if (Image.DetectFormat(imageBytes) is GifFormat)
            {
                var image = Image.Load<Rgb24>(imageBytes);
                var stopwatch = new Stopwatch();
                stopwatch.Start();
                var ret = image.Frames.AsEnumerable().Select((frame, frameIndex) =>
                {
                    stoppingToken.ThrowIfCancellationRequested();
                    var frameBytes = new Rgb24[frame.Width * frame.Height];
                    frame.CopyPixelDataTo(frameBytes);
                    var frameImage = Image.LoadPixelData<Rgb24>(frameBytes, frame.Width, frame.Height);
                    var stream = new MemoryStream();
                    frameImage.SaveAsPng(stream);
                    if (stream.TryGetBuffer(out var buffer))
                    {
                        var frameMat = Cv2.ImDecode(buffer, ImreadModes.Unchanged);
                        if (frameMat.Empty()) throw new($"Failed to decode frame {frameIndex} of image {imageId}.");
                        return new ImageKeyWithMatrix(imageId, (uint)frameIndex, frameMat);
                    }
                    throw new ObjectDisposedException(nameof(stream));
                }).ToList();
                _logger.LogTrace("Spending {}ms to Extracted {} frames out of GIF image {}",
                    stopwatch.ElapsedMilliseconds, image.Frames.Count, imageId);
                return ret;
            }
            throw new NotSupportedException($"Image {imageId} cannot decode by OpenCV and is not GIF format.");
        }
        finally
        {
            imageMat.Dispose();
        }
    }

    private static async Task ConsumeOcrConsumerWithAllScrips(
        ILifetimeScope scope,
        DbConnection parentConnection,
        DbTransaction parentTransaction,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default)
    {
        foreach (var script in new[] {"zh-Hans", "zh-Hant", "ja", "en"})
        {
            await using var scope1 = scope.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()(script);
            // https://learn.microsoft.com/en-us/ef/core/saving/transactions#share-connection-and-transaction
            db.Database.SetDbConnection(parentConnection);
            _ = await db.Database.UseTransactionAsync(parentTransaction, stoppingToken);

            var ocrConsumer = scope1.Resolve<OcrConsumer.New>()(script);
            await ocrConsumer.InitializePaddleOcr(stoppingToken);
            ocrConsumer.Consume(db, imageKeysWithMatrix, stoppingToken);
            _ = await db.SaveChangesAsync(stoppingToken);
        }
    }
}
