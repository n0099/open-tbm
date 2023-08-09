using System.Data;
using System.Data.Common;
using System.Diagnostics;
using Microsoft.EntityFrameworkCore.Storage;

namespace tbm.ImagePipeline;

public class ImageBatchConsumingWorker : ErrorableWorker
{
    private readonly ILogger<ImageBatchConsumingWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private readonly ChannelReader<List<ImageWithBytes>> _reader;

    public ImageBatchConsumingWorker(
        ILogger<ImageBatchConsumingWorker> logger,
        IHostApplicationLifetime applicationLifetime,
        ILifetimeScope scope0,
        Channel<List<ImageWithBytes>> channel
    ) : base(logger, applicationLifetime, shouldExitOnException: true, shouldExitOnFinish: true) =>
        (_logger, _scope0, _reader) = (logger, scope0, channel);

    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await foreach (var imagesWithBytes in _reader.ReadAllAsync(stoppingToken))
        {
            try
            {
                await using var scope1 = _scope0.BeginLifetimeScope();
                var db = scope1.Resolve<ImagePipelineDbContext.NewDefault>()();
                await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);

                var metadataConsumer = scope1.Resolve<MetadataConsumer>();
                metadataConsumer.Consume(db, imagesWithBytes, stoppingToken);
                var imageKeysWithMatrix = imagesWithBytes
                    .SelectMany(imageWithBytes => DecodeImageOrFramesBytes(imageWithBytes, stoppingToken)).ToList();
                try
                {
                    var hashConsumer = scope1.Resolve<HashConsumer>();
                    hashConsumer.Consume(db, imageKeysWithMatrix, stoppingToken);
                    var qrCodeConsumer = scope1.Resolve<QrCodeConsumer>();
                    qrCodeConsumer.Consume(db, imageKeysWithMatrix, stoppingToken);

                    _ = await db.SaveChangesAsync(stoppingToken);
                    await ConsumeOcrConsumer(scope1, db.Database.GetDbConnection(), transaction.GetDbTransaction(),
                        db.ForumScripts, imageKeysWithMatrix, stoppingToken);
                    await transaction.CommitAsync(stoppingToken);
                }
                finally
                {
                    imageKeysWithMatrix.ForEach(i => i.Matrix.Dispose());
                }
            }
            catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
            {
                throw;
            }
            catch (Exception e)
            {
                _logger.LogError(e, "Exception");
            }
        }
    }

    private IEnumerable<ImageKeyWithMatrix> DecodeImageOrFramesBytes
        (ImageWithBytes imageWithBytes, CancellationToken stoppingToken = default)
    {
        var imageBytes = imageWithBytes.Bytes;
        var imageId = imageWithBytes.ImageInReply.ImageId;
        // preserve alpha channel if there's any, so the type of mat might be CV_8UC3 or CV_8UC4
        var imageMat = Cv2.ImDecode(imageBytes, ImreadModes.Unchanged);
        if (!imageMat.Empty())
            return new ImageKeyWithMatrix[] {new(imageId, FrameIndex: 0, imageMat)};

        ImageKeyWithMatrix DecodeFrame(ImageFrame<Rgb24> frame, int frameIndex)
        {
            stoppingToken.ThrowIfCancellationRequested();
            var frameBytes = new Rgb24[frame.Width * frame.Height];
            frame.CopyPixelDataTo(frameBytes);
            var frameImage = Image.LoadPixelData<Rgb24>(frameBytes, frame.Width, frame.Height);
            var stream = new MemoryStream();
            frameImage.SaveAsPng(stream);
            if (!stream.TryGetBuffer(out var buffer))
                throw new ObjectDisposedException(nameof(stream));
            var frameMat = Cv2.ImDecode(buffer, ImreadModes.Unchanged);
            if (frameMat.Empty())
                throw new($"Failed to decode frame {frameIndex} of image {imageId}.");
            return new(imageId, (uint)frameIndex, frameMat);
        }

        try
        {
            if (Image.DetectFormat(imageBytes) is GifFormat)
            {
                var image = Image.Load<Rgb24>(imageBytes);
                var stopwatch = new Stopwatch();
                stopwatch.Start();
                var ret = image.Frames.AsEnumerable().Select(DecodeFrame).ToList();
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

    private static async Task ConsumeOcrConsumer(
        ILifetimeScope scope,
        DbConnection parentConnection,
        DbTransaction parentTransaction,
        IQueryable<ForumScript> forumScripts,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithMatrix,
        CancellationToken stoppingToken = default)
    {
        foreach (var scriptsGroupByFid in forumScripts.GroupBy(e => e.Fid, e => e.Script).ToList())
        {
            await using var scope1 = scope.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()(scriptsGroupByFid.Key, "");
            db.Database.SetDbConnection(parentConnection);
            _ = await db.Database.UseTransactionAsync(parentTransaction, stoppingToken);
            var imagesInCurrentFid = imageKeysWithMatrix.IntersectBy(
                db.ReplyContentImages.Where(replyContentImage => imageKeysWithMatrix
                        .Select(imageKeyWithMatrix => imageKeyWithMatrix.ImageId)
                        .Contains(replyContentImage.ImageId))
                    .Select(replyContentImage => replyContentImage.ImageId),
                imageKeyWithMatrix => imageKeyWithMatrix.ImageId).ToList();
            foreach (var script in scriptsGroupByFid)
                await ConsumeByFidAndScript(scriptsGroupByFid.Key, script, imagesInCurrentFid);
        }
        async Task ConsumeByFidAndScript(Fid fid, string script, IEnumerable<ImageKeyWithMatrix> imagesInCurrentFid)
        {
            await using var scope1 = scope.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()(fid, script);
            // https://learn.microsoft.com/en-us/ef/core/saving/transactions#share-connection-and-transaction
            db.Database.SetDbConnection(parentConnection);
            _ = await db.Database.UseTransactionAsync(parentTransaction, stoppingToken);

            var ocrConsumer = scope1.Resolve<OcrConsumer.New>()(script);
            await ocrConsumer.InitializePaddleOcr(stoppingToken);
            ocrConsumer.Consume(db, imagesInCurrentFid, stoppingToken);
            _ = await db.SaveChangesAsync(stoppingToken);
        }
    }
}
