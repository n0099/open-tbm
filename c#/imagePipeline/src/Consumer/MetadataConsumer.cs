using System.IO.Hashing;
using System.Text.Json;
using SixLabors.ImageSharp.Formats.Bmp;
using SixLabors.ImageSharp.Formats.Gif;
using SixLabors.ImageSharp.Formats.Jpeg;
using SixLabors.ImageSharp.Formats.Png;
using SixLabors.ImageSharp.Metadata.Profiles.Exif;

namespace tbm.ImagePipeline.Consumer;

public class MetadataConsumer
{
    private readonly ulong[] _commonIccProfilesXxHash3ToIgnore;

    public MetadataConsumer(IConfiguration config) =>
        _commonIccProfilesXxHash3ToIgnore = config.GetSection("MetadataConsumer")
            .GetSection("CommonIccProfilesXxHash3ToIgnore").Get<ulong[]>() ?? Array.Empty<ulong>();

    public void Consume(
        ImagePipelineDbContext db,
        IEnumerable<ImageWithBytes> imagesWithBytes,
        CancellationToken stoppingToken = default) =>
        db.ImageMetadata.AddRange(imagesWithBytes.Select(imageWithBytes =>
        {
            stoppingToken.ThrowIfCancellationRequested();
            var (image, imageBytes) = imageWithBytes;
            var info = Image.Identify(imageBytes);
            var meta = info.Metadata;
            if (meta.DecodedImageFormat is not (JpegFormat or PngFormat or GifFormat or BmpFormat))
                ThrowHelper.ThrowNotSupportedException($"Not supported image format {meta.DecodedImageFormat?.Name}.");

            T? GetExifTagValueOrNull<T>(ExifTag<T> tag) where T : class =>
                meta.ExifProfile.TryGetValue(tag, out var value) ? value.Value : null;

            var iccProfileRawBytes = meta.IccProfile?.ToByteArray();
            var iccProfile = meta.IccProfile == null ? null : new ImageMetadata.Icc
            {
                XxHash3 = XxHash3.HashToUInt64(iccProfileRawBytes),
                RawBytes = iccProfileRawBytes!
            };
            if (iccProfile != null && _commonIccProfilesXxHash3ToIgnore.Contains(iccProfile.XxHash3))
                iccProfile = null;

            return new ImageMetadata
            {
                ImageId = image.ImageId,
                Format = meta.DecodedImageFormat?.Name,
                Width = (ushort)info.Width,
                Height = (ushort)info.Height,
                BitsPerPixel = (ushort)info.PixelType.BitsPerPixel,
                FrameCount = (uint)info.FrameMetadataCollection.Count,
                EmbeddedOther = (meta.IptcProfile, meta.XmpProfile) == default // is all null
                    ? null
                    : new()
                    {
                        Iptc = meta.IptcProfile?.Data,
                        Xmp = meta.XmpProfile?.ToByteArray()
                    },
                EmbeddedExif = meta.ExifProfile == null ? null : new ImageMetadata.Exif
                { // https://exiftool.org/TagNames/EXIF.html, https://exiv2.org/tags.html
                    Orientation = meta.ExifProfile.TryGetValue(ExifTag.Orientation, out var orientation)
                        ? Enum.GetName((ImageMetadata.Exif.ExifOrientation)orientation.Value)
                        : null,
                    Make = GetExifTagValueOrNull(ExifTag.Make).NullIfEmpty(),
                    Model = GetExifTagValueOrNull(ExifTag.Model).NullIfEmpty(),
                    CreateDate = GetExifTagValueOrNull(ExifTag.DateTimeDigitized).NullIfEmpty(),
                    ModifyDate = GetExifTagValueOrNull(ExifTag.DateTime).NullIfEmpty(),
                    TagNames = JsonSerializer.Serialize(meta.ExifProfile.Values.Select(i => i.Tag.ToString())),
                    RawBytes = meta.ExifProfile.ToByteArray() ?? throw new NullReferenceException()
                },
                EmbeddedIcc = iccProfile,
                JpgMetadata = ImageMetadata.Jpg.FromImageSharpMetadata(meta),
                PngMetadata = ImageMetadata.Png.FromImageSharpMetadata(meta),
                GifMetadata = ImageMetadata.Gif.FromImageSharpMetadata(meta),
                BmpMetadata = ImageMetadata.Bmp.FromImageSharpMetadata(meta),
                DownloadedByteSize = image.ByteSize == imageBytes.Length
                    ? null
                    : new() {DownloadedByteSize = (uint)imageBytes.Length},
                XxHash3 = XxHash3.HashToUInt64(imageBytes)
            };
        }));
}
