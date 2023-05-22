using System.IO.Hashing;
using System.Text.Json;
using SixLabors.ImageSharp.Formats.Gif;
using SixLabors.ImageSharp.Formats.Jpeg;
using SixLabors.ImageSharp.Formats.Png;
using SixLabors.ImageSharp.Metadata.Profiles.Exif;

namespace tbm.ImagePipeline.Consumer;

public class MetadataConsumer
{
    public static void Consume(
        ImagePipelineDbContext db,
        ImageAndBytesKeyById imageAndBytesKeyById,
        CancellationToken stoppingToken) =>
        db.ImageMetadata.AddRange(imageAndBytesKeyById.Select(pair =>
        {
            var (imageId, (image, imageBytes)) = pair;
            var info = Image.Identify(imageBytes);
            var meta = info.Metadata;
            if (meta.DecodedImageFormat is not (JpegFormat or PngFormat or GifFormat))
                ThrowHelper.ThrowNotSupportedException($"Not supported image format {meta.DecodedImageFormat?.Name}.");
            T? GetExifTagValueOrNull<T>(ExifTag<T> tag) where T : class =>
                meta.ExifProfile.TryGetValue(tag, out var value) ? value.Value : null;
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
                        Exif = meta.ExifProfile == null ? null : new ImageMetadata.Embedded.EmbeddedExif
                        { // https://exiftool.org/TagNames/EXIF.html, https://exiv2.org/tags.html
                            Orientation = meta.ExifProfile.TryGetValue(ExifTag.Orientation, out var orientation)
                                ? Enum.GetName((ImageMetadata.Embedded.EmbeddedExif.ExifOrientation)orientation.Value)
                                : null,
                            Make = GetExifTagValueOrNull(ExifTag.Make),
                            Model = GetExifTagValueOrNull(ExifTag.Model),
                            CreateDate =GetExifTagValueOrNull(ExifTag.DateTimeDigitized),
                            ModifyDate = GetExifTagValueOrNull(ExifTag.DateTime),
                            TagNames = JsonSerializer.Serialize(meta.ExifProfile.Values.Select(i => i.Tag.ToString())),
                            RawBytes = meta.ExifProfile.ToByteArray() ?? throw new NullReferenceException()
                        },
                        Icc = meta.IccProfile?.ToByteArray(),
                        Iptc = meta.IptcProfile?.Data,
                        Xmp = meta.XmpProfile?.ToByteArray()
                    },
                JpgMetadata = ImageMetadata.Jpg.FromImageSharpMetadata(meta),
                PngMetadata = ImageMetadata.Png.FromImageSharpMetadata(meta),
                GifMetadata = ImageMetadata.Gif.FromImageSharpMetadata(meta),
                DownloadedBytesSize = image.BytesSize == imageBytes.Length
                    ? null
                    : new() {DownloadedBytesSize = (uint)imageBytes.Length},
                XxHash3 = XxHash3.HashToUInt64(imageBytes)
            };
        }));
}
