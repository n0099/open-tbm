using System.Data;
using System.Data.Common;
using System.Diagnostics;
using System.Linq.Expressions;
using Microsoft.EntityFrameworkCore.Storage;

namespace tbm.ImagePipeline;

public class ImageBatchConsumingWorker(
        ILogger<ImageBatchConsumingWorker> logger,
        ILifetimeScope scope0,
        Channel<List<ImageWithBytes>> channel)
    : ErrorableWorker(shouldExitOnException: true, shouldExitOnFinish: true)
{
    protected override async Task DoWork(CancellationToken stoppingToken)
    {
        await foreach (var imagesWithBytes in channel.Reader.ReadAllAsync(stoppingToken))
        {
            try
            {
                await Consume(imagesWithBytes, stoppingToken);
            }
            catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
            {
                throw;
            }
            catch (Exception e)
            {
                logger.LogError(e, "Exception");
            }
            if (!(channel.Reader.Completion.IsCompleted || channel.Reader.TryPeek(out _)))

                // https://stackoverflow.com/questions/72972469/which-is-the-fastest-way-to-tell-if-a-channelt-is-empty
                logger.LogWarning("Consumer is idle due to no image batch getting produced, configure \"ImageBatchProducer.MaxBufferedImageBatches\""
                                  + " to a larger value then restart will allow more image batches to get downloaded before consume");
        }
        logger.LogInformation("No more image batch to consume, configure \"ImageBatchProducer.StartFromLatestSuccessful\""
                              + " to false then restart will rerun all previous failed image batches from start");
    }

    private async Task Consume(IReadOnlyCollection<ImageWithBytes> imagesWithBytes, CancellationToken stoppingToken = default)
    {
        await using var scope1 = scope0.BeginLifetimeScope(builder =>
            builder.RegisterType<FailedImageHandler>()
                .WithParameter(new NamedParameter("stoppingToken", stoppingToken))
                .SingleInstance()); // only in this and nested scopes
        var db = scope1.Resolve<ImagePipelineDbContext.NewDefault>()();
        await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);

        var imagesInReply = imagesWithBytes.Select(i => i.ImageInReply).ToList();
        db.AttachRange(imagesInReply);
        void MarkImagesInReplyAsConsumed
            (Expression<Func<ImageInReply, bool>> selector, IEnumerable<ImageId> imagesIdToUpdate) =>
            db.ChangeTracker.Entries<ImageInReply>()
                .IntersectBy(imagesInReply
                    .IntersectBy(imagesIdToUpdate, i => i.ImageId), entry => entry.Entity)
                .ForEach(entry => entry.Property(selector).CurrentValue = true);

        var imagesId = string.Join(",", imagesInReply.Select(i => i.ImageId));
        logger.LogTrace("Start to consume {} image(s): [{}]", imagesWithBytes.Count, imagesId);
        var sw = new Stopwatch();
        void LogStopwatch(string consumerType) =>
            logger.LogTrace("Spend {}ms to {} for {} image(s): [{}]",
                sw.ElapsedMilliseconds, consumerType, imagesWithBytes.Count, imagesId);

        void ConsumeConsumer<T>(
            Expression<Func<ImageInReply, bool>> selector, IEnumerable<T> images,
            IConsumer<T> consumer, string consumerType)
        {
            sw.Restart();
            var (failed, consumed) = consumer.Consume(db, images, stoppingToken);
            LogStopwatch(consumerType);
            MarkImagesInReplyAsConsumed(selector, consumed);

            var failedImagesId = failed.ToList();
            if (!failedImagesId.Any()) return;
            logger.LogError("Failed to {} for {} image(s): [{}]",
                consumerType, failedImagesId.Count, string.Join(",", failedImagesId));
        }

        ConsumeConsumer(i => i.MetadataConsumed,
            imagesWithBytes.Where(i => !i.ImageInReply.MetadataConsumed),
            scope1.Resolve<MetadataConsumer>(), "extract metadata");

        var failedImageHandler = scope1.Resolve<FailedImageHandler>();
        var imageKeysWithMatrix = failedImageHandler.TrySelect(imagesWithBytes
                    .Where(i => i.ImageInReply is not {HashConsumed: true, QrCodeConsumed: true, OcrConsumed: true}),
                imageWithBytes => imageWithBytes.ImageInReply.ImageId,
                DecodeImageOrFramesBytes(stoppingToken))
            .Rights().SelectMany(i => i).ToList();
        try
        {
            IEnumerable<ImageKeyWithMatrix> ExceptConsumed
                (Func<ImageInReply, bool> selector) => imageKeysWithMatrix
                .ExceptBy(imagesInReply.Where(selector)
                    .Select(i => i.ImageId), i => i.ImageId);
            ConsumeConsumer(i => i.HashConsumed,
                ExceptConsumed(i => i.HashConsumed),
                scope1.Resolve<HashConsumer>(), "calculate hash");
            ConsumeConsumer(i => i.QrCodeConsumed,
                ExceptConsumed(i => i.QrCodeConsumed),
                scope1.Resolve<QrCodeConsumer>(), "scan QRCode");
            await ConsumeOcrConsumer(consumedImagesId =>
                    MarkImagesInReplyAsConsumed(i => i.OcrConsumed, consumedImagesId),
                ExceptConsumed(i => i.OcrConsumed).ToList(),
                scope1, db.Database.GetDbConnection(), transaction.GetDbTransaction(), db.ForumScripts, stoppingToken);
        }
        finally
        {
            imageKeysWithMatrix.ForEach(i => i.Matrix.Dispose());
        }

        failedImageHandler.SaveFailedImages(db);
        _ = await db.SaveChangesAsync(stoppingToken); // https://github.com/dotnet/EntityFramework.Docs/pull/4358
        await transaction.CommitAsync(stoppingToken);
    }

    private Func<ImageWithBytes, IEnumerable<ImageKeyWithMatrix>> DecodeImageOrFramesBytes
        (CancellationToken stoppingToken = default) => imageWithBytes =>
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
            using var frameImage = Image.LoadPixelData<Rgb24>(frameBytes, frame.Width, frame.Height);
            using var stream = new MemoryStream();
            frameImage.SaveAsPng(stream);
            if (!stream.TryGetBuffer(out var buffer))
                throw new ObjectDisposedException(nameof(stream));
#pragma warning disable IDISP001 // Dispose created
            var frameMat = Cv2.ImDecode(buffer, ImreadModes.Unchanged);
#pragma warning restore IDISP001 // Dispose created
            return frameMat.Empty()
                ? throw new InvalidOperationException(
                    $"Failed to decode frame {frameIndex} of image {imageId}.")
                : new(imageId, (uint)frameIndex, frameMat);
        }

        try
        {
            if (Image.DetectFormat(imageBytes) is GifFormat)
            {
                using var image = Image.Load<Rgb24>(imageBytes);
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
    };

    private async Task ConsumeOcrConsumer(
        Action<IEnumerable<ImageId>> markImageInReplyAsConsumed,
        IReadOnlyCollection<ImageKeyWithMatrix> imageKeysWithMatrix,
        ILifetimeScope scope,
        DbConnection parentConnection,
        DbTransaction parentTransaction,
        IQueryable<ForumScript> forumScripts,
        CancellationToken stoppingToken = default)
    {
        foreach (var scriptsGroupByFid in forumScripts.GroupBy(e => e.Fid, e => e.Script).ToList())
        {
            await using var scope1 = scope.BeginLifetimeScope();
            var db = scope1.Resolve<ImagePipelineDbContext.New>()(scriptsGroupByFid.Key, "");
            db.Database.SetDbConnection(parentConnection);
            _ = await db.Database.UseTransactionAsync(parentTransaction, stoppingToken);

            // try to know which fid owns current image batch
            var imagesInCurrentFid = imageKeysWithMatrix
                .IntersectBy(
                    from replyContentImage in db.ReplyContentImages
                    where imageKeysWithMatrix
                        .Select(imageKeyWithMatrix => imageKeyWithMatrix.ImageId)
                        .Contains(replyContentImage.ImageId)
                    select replyContentImage.ImageId,
                    imageKeyWithMatrix => imageKeyWithMatrix.ImageId)
                .ToList();
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
            var (failed, consumed) = ocrConsumer.Consume(db, imagesInCurrentFid, stoppingToken);
            sw.Stop();

            markImageInReplyAsConsumed(consumed);
            _ = await db.SaveChangesAsync(stoppingToken);

            var failedImagesId = failed.ToList();
            if (failedImagesId.Any())
                logger.LogError("Failed to detect and recognize {} script text for fid {} in {} image(s): [{}]",
                    script, fid, failedImagesId.Count, string.Join(",", failedImagesId));
            logger.LogTrace("Spend {}ms to detect and recognize {} script text for fid {} in {} image(s): [{}]",
                sw.ElapsedMilliseconds, script, fid, imagesInCurrentFid.Count,
                string.Join(",", imagesInCurrentFid.Select(i => i.ImageId)));
        }
    }
}
