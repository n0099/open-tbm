using System.IO.Hashing;
using SixLabors.ImageSharp.Formats.Jpeg;

namespace tbm.ImagePipeline.Consumer;

public class MetadataConsumer
{
    public static void Consume(
        ImagePipelineDbContext db,
        Dictionary<ImageId, (TiebaImage Image, byte[] Bytes)> imageAndBytesKeyById,
        CancellationToken stoppingToken) =>
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
                PngMetadata = ImageMetadata.Png.FromImageSharpMetadata(meta),
                GifMetadata = ImageMetadata.Gif.FromImageSharpMetadata(meta),
                DownloadedByteSize = image.ByteSize == imageBytes.Length
                    ? null
                    : new() {DownloadedByteSize = (uint)imageBytes.Length},
                XxHash3 = XxHash3.HashToUInt64(imageBytes)
            };
        }));
}
