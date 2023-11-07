using System.Data;
using System.Data.Common;
using System.Diagnostics;
using Microsoft.EntityFrameworkCore.Storage;

namespace tbm.ImagePipeline;

public class ImageBatchConsumingWorker(
        ILogger<ImageBatchConsumingWorker> logger,
        IHostApplicationLifetime applicationLifetime,
        ILifetimeScope scope0,
        Channel<List<ImageWithBytes>> channel)
    : ErrorableWorker(logger, applicationLifetime, shouldExitOnException: true, shouldExitOnFinish: true)
{
    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await foreach (var imagesWithBytes in channel.Reader.ReadAllAsync(stoppingToken))
        {
            try
            {
                await using var scope1 = scope0.BeginLifetimeScope("ImageBatchConsumingWorker",
                    b => b.Register(_ => stoppingToken).AsSelf());
                var db = scope1.Resolve<ImagePipelineDbContext.NewDefault>()();
                await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);

                logger.LogTrace("Start to consume {} image(s): [{}]",
                    imagesWithBytes.Count,
                    string.Join(",", imagesWithBytes.Select(i => i.ImageInReply.ImageId)));
                var sw = new Stopwatch();
                void LogStopwatch(string consumerType) =>
                    logger.LogTrace("Spend {}ms to {} for {} image(s): [{}]",
                        sw.ElapsedMilliseconds, consumerType, imagesWithBytes.Count,
                        string.Join(",", imagesWithBytes.Select(i => i.ImageInReply.ImageId)));

                var metadataConsumer = scope1.Resolve<MetadataConsumer>();
                sw.Restart();
                metadataConsumer.Consume(db, imagesWithBytes, stoppingToken);
                LogStopwatch("extract metadata");
                var imageKeysWithMatrix = imagesWithBytes
                    .SelectMany(imageWithBytes => DecodeImageOrFramesBytes(imageWithBytes, stoppingToken)).ToList();
                try
                {
                    var hashConsumer = scope1.Resolve<HashConsumer>();
                    sw.Restart();
                    hashConsumer.Consume(db, imageKeysWithMatrix, stoppingToken);
                    LogStopwatch("calculate hash");

                    var qrCodeConsumer = scope1.Resolve<QrCodeConsumer>();
                    sw.Restart();
                    qrCodeConsumer.Consume(db, imageKeysWithMatrix, stoppingToken);
                    LogStopwatch("scan QRCode");

                    _ = await db.SaveChangesAsync(stoppingToken); // https://github.com/dotnet/EntityFramework.Docs/pull/4358
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
                logger.LogError(e, "Exception");
            }
            if (!channel.Reader.TryPeek(out _)) // https://stackoverflow.com/questions/72972469/which-is-the-fastest-way-to-tell-if-a-channelt-is-empty
                logger.LogWarning("Consumer is idle due to no image batch getting produced, configure \"ImageBatchProducer.MaxBufferedImageBatches\""
                                  + " to a larger value then restart will allow more image batches to get downloaded before consume");
        }
        logger.LogInformation("No more image batch to consume, configure \"ImageBatchProducer.StartFromLatestSuccessful\""
                              + " to false then restart will rerun all previous failed image batches from start");
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
                logger.LogTrace("Spending {}ms to Extracted {} frames out of GIF image {}",
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

    private async Task ConsumeOcrConsumer(
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
            // try to know which fid owns current image batch
            var imagesInCurrentFid = imageKeysWithMatrix.IntersectBy(
                from replyContentImage in db.ReplyContentImages
                where imageKeysWithMatrix
                    .Select(imageKeyWithMatrix => imageKeyWithMatrix.ImageId)
                    .Contains(replyContentImage.ImageId)
                select replyContentImage.ImageId,
                imageKeyWithMatrix => imageKeyWithMatrix.ImageId).ToList();
            if (imagesInCurrentFid.Count == 0) continue;
            foreach (var script in scriptsGroupByFid)
                await ConsumeByFidAndScript(scriptsGroupByFid.Key, script, imagesInCurrentFid);
        }
        async Task ConsumeByFidAndScript(Fid fid, string script, IReadOnlyCollection<ImageKeyWithMatrix> imagesInCurrentFid)
        {
            await using var scope1 = scope.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()(fid, script);
            // https://learn.microsoft.com/en-us/ef/core/saving/transactions#share-connection-and-transaction
            db.Database.SetDbConnection(parentConnection);
            _ = await db.Database.UseTransactionAsync(parentTransaction, stoppingToken);

            var ocrConsumer = scope1.Resolve<OcrConsumer.New>()(script);
            await ocrConsumer.InitializePaddleOcr(stoppingToken);
            var sw = new Stopwatch();
            sw.Start();
            ocrConsumer.Consume(db, imagesInCurrentFid, stoppingToken);
            logger.LogTrace("Spend {}ms to detect and recognize {} script text for fid {} in {} image(s): [{}]",
                sw.ElapsedMilliseconds, script, fid, imagesInCurrentFid.Count,
                string.Join(",", imagesInCurrentFid.Select(i => i.ImageId)));
            _ = await db.SaveChangesAsync(stoppingToken);
        }
    }
}
