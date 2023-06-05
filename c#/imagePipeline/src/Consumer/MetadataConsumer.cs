using System.Globalization;
using System.IO.Hashing;
using System.Text.RegularExpressions;
using SixLabors.ImageSharp.Metadata.Profiles.Exif;

namespace tbm.ImagePipeline.Consumer;

public partial class MetadataConsumer
{

    [GeneratedRegex( // should able to guess malformed string, e.g. 2018:09:08 15:288
        "^(?<year>(?:19|20|21)[0-9]{2}):?(?<month>[0-1]?[0-9]):?(?<day>[0-1]?[0-9]) (?<hour>[0-2]?[0-9]):?(?<minute>[0-5]?[0-9]):?(?<second>[0-5]?[0-9])$",
        RegexOptions.Compiled, matchTimeoutMilliseconds: 100)]
    private static partial Regex ExtractMalformedExifDateTimeRegex();

    private readonly ILogger<MetadataConsumer> _logger;
    private readonly ulong[] _commonIccProfilesXxHash3ToIgnore;

    public MetadataConsumer(ILogger<MetadataConsumer> logger, IConfiguration config)
    {
        _logger = logger;
        _commonIccProfilesXxHash3ToIgnore = config.GetSection("MetadataConsumer")
            .GetSection("CommonIccProfilesXxHash3ToIgnore").Get<ulong[]>() ?? Array.Empty<ulong>();
    }

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
                iccProfile.RawBytes = null;

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
                    CreateDate = ParseExifDateTimeOrNull(GetExifTagValueOrNull(ExifTag.DateTimeDigitized)),
                    ModifyDate = ParseExifDateTimeOrNull(GetExifTagValueOrNull(ExifTag.DateTime)),
                    TagNames = JsonSerializer.Serialize(meta.ExifProfile.Values.Select(i => i.Tag.ToString())),
                    RawBytes = meta.ExifProfile.ToByteArray() ?? throw new NullReferenceException()
                },
                EmbeddedIcc = iccProfile,
                JpgMetadata = ImageMetadata.Jpg.FromImageSharpMetadata(meta),
                PngMetadata = ImageMetadata.Png.FromImageSharpMetadata(meta),
                GifMetadata = ImageMetadata.Gif.FromImageSharpMetadata(meta),
                BmpMetadata = ImageMetadata.Bmp.FromImageSharpMetadata(meta),
                DownloadedByteSize = image.ExpectedByteSize == imageBytes.Length
                    ? null
                    : new() {DownloadedByteSize = (uint)imageBytes.Length},
                XxHash3 = XxHash3.HashToUInt64(imageBytes)
            };
        }));

    private DateTime? ParseExifDateTimeOrNull(string? exifDateTime)
    {
        static DateTime? ParseDateTimeWithFormatOrNull(string? dateTime) =>
            DateTime.TryParseExact(dateTime, "yyyy:M:d H:m:s", CultureInfo.InvariantCulture,
                DateTimeStyles.None, out var ret) ? ret : null;

        if (string.IsNullOrEmpty(exifDateTime)) return null;
        var originalDateTime = ParseDateTimeWithFormatOrNull(exifDateTime);
        if (originalDateTime != null) return originalDateTime;

        // try to extract parts in malformed date time then try parse the parts composed formatted string
        // e.g. 2018:09:08 15:288 -> 2018:09:08 15:28:08
        // doing this should extract date time values from raw EXIF bytes as much as possible
        // since they usually only done by once for all
        var match = ExtractMalformedExifDateTimeRegex().Match(exifDateTime);
        if (!match.Success)
        {
            _logger.LogWarning("Unable to extract parts from malformed exif date time {}", exifDateTime);
            return null;
        }
        var ret = ParseDateTimeWithFormatOrNull( // sync with format "yyyy:M:d H:m:s"
            $"{match.Groups["year"]}:{match.Groups["month"]}:{match.Groups["day"]} "
            + $"{match.Groups["hour"]}:{match.Groups["minute"]}:{match.Groups["second"]}");
        if (ret == null)
            _logger.LogWarning("Unable to extract parts from malformed exif date time {}", exifDateTime);
        else
            _logger.LogWarning("Converted malformed exif date time {} to {:yyyy:MM:D HH:mm:ss}",
                exifDateTime, ret);
        return ret;
    }
}
