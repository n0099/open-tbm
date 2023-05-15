using System.Data;
using System.IO.Hashing;
using SixLabors.ImageSharp.Formats.Jpeg;

namespace tbm.ImagePipeline.Consumer;

public class MetadataConsumer
{
    private readonly ImagePipelineDbContext.New _dbContextFactory;

    public MetadataConsumer(ImagePipelineDbContext.New dbContextFactory) => _dbContextFactory = dbContextFactory;

    public async Task Consume(Dictionary<ImageId, (TiebaImage Image, byte[] Bytes)> imageAndBytesKeyById, CancellationToken stoppingToken)
    {
        var db = _dbContextFactory("");
        await using var transaction = await db.Database.BeginTransactionAsync(IsolationLevel.ReadCommitted, stoppingToken);

        db.ImageMetadata.AddRange(imageAndBytesKeyById.Select(pair =>
        {
            var (imageId, (image, imageBytes)) = pair;
            var info = Image.Identify(imageBytes);
            var meta = info.Metadata;
            return new ImageMetadata
            {
                ImageId = imageId,
                Format = meta.DecodedImageFormat?.Name,
                Width = (ushort)info.Width,
                Height = (ushort)info.Height,
                BitsPerPixel = (ushort)info.PixelType.BitsPerPixel,
                FrameCount = (ushort)info.FrameMetadataCollection.Count,
                EmbeddedMetadata = (meta.ExifProfile, meta.IccProfile, meta.IptcProfile, meta.XmpProfile) == default // is all null
                    ? null
                    : new()
                    {
                        Exif = meta.ExifProfile?.ToByteArray(),
                        Icc = meta.IccProfile?.ToByteArray(),
                        Iptc = meta.IptcProfile?.Data,
                        Xmp = meta.XmpProfile?.ToByteArray()
                    },
                JpgMetadata = ImageMetadata.Jpg.FromImageSharpMetadata(meta),
                DownloadedByteSize = image.ByteSize == imageBytes.Length
                    ? null
                    : new() {DownloadedByteSize = (uint)imageBytes.Length},
                XxHash3 = XxHash3.HashToUInt64(imageBytes)
            };
        }));

        _ = await db.SaveChangesAsync(stoppingToken);
        await transaction.CommitAsync(stoppingToken);
    }
}
