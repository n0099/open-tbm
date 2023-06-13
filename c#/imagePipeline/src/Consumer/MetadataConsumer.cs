using System.Diagnostics.CodeAnalysis;
using System.Globalization;
using System.IO.Hashing;
using System.Text.RegularExpressions;
using SixLabors.ImageSharp.Metadata.Profiles.Exif;
using SixLabors.ImageSharp.Metadata.Profiles.Icc;
using SixLabors.ImageSharp.Metadata.Profiles.Iptc;
using SixLabors.ImageSharp.Metadata.Profiles.Xmp;

namespace tbm.ImagePipeline.Consumer;

public partial class MetadataConsumer
{

    [GeneratedRegex( // should able to guess malformed string, e.g. 2018:09:08 15:288
        "^(?<year>(?:19|20|21)[0-9]{2}):?(?<month>[0-1]?[0-9]):?(?<day>[0-1]?[0-9]) (?<hour>[0-2]?[0-9]):?(?<minute>[0-5]?[0-9]):?(?<second>[0-5]?[0-9])$",
        RegexOptions.Compiled, matchTimeoutMilliseconds: 100)]
    private static partial Regex ExtractMalformedExifDateTimeRegex();

    private readonly ILogger<MetadataConsumer> _logger;
    private readonly (ulong[] Exif, ulong[] Icc, ulong[] Iptc, ulong[] Xmp) _commonEmbeddedMetadataXxHash3ToIgnore;

    public MetadataConsumer(ILogger<MetadataConsumer> logger, IConfiguration config)
    {
        _logger = logger;
        var section = config.GetSection("MetadataConsumer").GetSection("CommonEmbeddedMetadataXxHash3ToIgnore");
        ulong[] GetCommonXxHash3ToIgnore(string key) => section.GetSection(key).Get<ulong[]>() ?? Array.Empty<ulong>();
        _commonEmbeddedMetadataXxHash3ToIgnore = (
            Exif: GetCommonXxHash3ToIgnore("Exif"),
            Icc: GetCommonXxHash3ToIgnore("Icc"),
            Iptc: GetCommonXxHash3ToIgnore("Iptc"),
            Xmp: GetCommonXxHash3ToIgnore("Xmp")
        );
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

            return new ImageMetadata
            {
                ImageId = image.ImageId,
                Format = meta.DecodedImageFormat?.Name,
                Width = (ushort)info.Width,
                Height = (ushort)info.Height,
                BitsPerPixel = (ushort)info.PixelType.BitsPerPixel,
                FrameCount = (uint)info.FrameMetadataCollection.Count,
                XxHash3 = XxHash3.HashToUInt64(imageBytes),
                DownloadedByteSize = image.ExpectedByteSize == imageBytes.Length
                    ? null
                    : new() {DownloadedByteSize = (uint)imageBytes.Length},
                EmbeddedExif = CreateEmbeddedExifFromProfile(meta.ExifProfile),
                EmbeddedIcc = CreateEmbeddedFromProfile<IccProfile, ImageMetadata.Icc>
                    (_commonEmbeddedMetadataXxHash3ToIgnore.Icc, meta.IccProfile, i => i?.ToByteArray()),
                EmbeddedIptc = CreateEmbeddedFromProfile<IptcProfile, ImageMetadata.Iptc>
                    (_commonEmbeddedMetadataXxHash3ToIgnore.Iptc, meta.IptcProfile, i => i?.Data),
                EmbeddedXmp = CreateEmbeddedFromProfile<XmpProfile, ImageMetadata.Xmp>
                    (_commonEmbeddedMetadataXxHash3ToIgnore.Xmp, meta.XmpProfile, i => i?.ToByteArray()),
                JpgMetadata = ImageMetadata.Jpg.FromImageSharpMetadata(meta),
                PngMetadata = ImageMetadata.Png.FromImageSharpMetadata(meta),
                GifMetadata = ImageMetadata.Gif.FromImageSharpMetadata(meta),
                BmpMetadata = ImageMetadata.Bmp.FromImageSharpMetadata(meta)
            };
        }));

    [return: NotNullIfNotNull(nameof(profile))]
    private static TEmbeddedMetadata? CreateEmbeddedFromProfile<TImageSharpProfile, TEmbeddedMetadata>(
        IEnumerable<ulong> commonXxHash3ToIgnore,
        TImageSharpProfile? profile,
        Func<TImageSharpProfile?, byte[]?> rawBytesSelector
    )
        where TEmbeddedMetadata : class, ImageMetadata.IEmbedded, new()
    {
        var rawBytes = rawBytesSelector(profile); // will be null when param profile is null
        var xxHash3 = XxHash3.HashToUInt64(rawBytes);
        return profile == null ? null : new TEmbeddedMetadata
        {
            XxHash3 = xxHash3,
            RawBytes = commonXxHash3ToIgnore.Contains(xxHash3) ? null : rawBytes
        };
    }

    private ImageMetadata.Exif? CreateEmbeddedExifFromProfile(ExifProfile? exif)
    {
        T? GetExifTagValueOrNull<T>(ExifTag<T> tag) where T : class =>
            exif.TryGetValue(tag, out var value) ? value.Value : null;
        T? GetExifTagValueOrNull2<T>(ExifTag<T> tag) where T : struct =>
            exif.TryGetValue(tag, out var value) ? value.Value : null;

        var ret = CreateEmbeddedFromProfile<ExifProfile, ImageMetadata.Exif>
            (_commonEmbeddedMetadataXxHash3ToIgnore.Exif, exif, i => i?.ToByteArray());
        if (ret != null && exif != null)
        { // https://exiftool.org/TagNames/EXIF.html, https://exiv2.org/tags.html
            ret.Orientation = exif.TryGetValue(ExifTag.Orientation, out var orientation)
                ? Enum.GetName((ImageMetadata.Exif.ExifOrientation)orientation.Value)
                : null;
            ret.ImageDescription = GetExifTagValueOrNull(ExifTag.ImageDescription).NullIfEmpty();
            ret.UserComment = GetExifTagValueOrNull2(ExifTag.UserComment).ToString().NullIfEmpty();
            ret.Artist = GetExifTagValueOrNull(ExifTag.Artist).NullIfEmpty();
            ret.XpAuthor = GetExifTagValueOrNull(ExifTag.XPAuthor).NullIfEmpty();
            ret.Copyright = GetExifTagValueOrNull(ExifTag.Copyright).NullIfEmpty();
            ret.ImageUniqueId = GetExifTagValueOrNull(ExifTag.ImageUniqueID).NullIfEmpty();
            ret.BodySerialNumber = GetExifTagValueOrNull(ExifTag.SerialNumber).NullIfEmpty();
            ret.Make = GetExifTagValueOrNull(ExifTag.Make).NullIfEmpty();
            ret.Model = GetExifTagValueOrNull(ExifTag.Model).NullIfEmpty();
            ret.Software = GetExifTagValueOrNull(ExifTag.Software).NullIfEmpty();
            ret.CustomRendered = GetExifTagValueOrNull2(ExifTag.CustomRendered);
            ret.DateTime = ParseExifDateTimeOrNull(GetExifTagValueOrNull(ExifTag.DateTime));
            ret.DateTimeDigitized = ParseExifDateTimeOrNull(GetExifTagValueOrNull(ExifTag.DateTimeDigitized));
            ret.DateTimeOriginal = ParseExifDateTimeOrNull(GetExifTagValueOrNull(ExifTag.DateTimeOriginal));
            ret.SubsecTime = ParseExifDateTimeOrNull(GetExifTagValueOrNull(ExifTag.SubsecTime));
            ret.SubsecTimeDigitized = ParseExifDateTimeOrNull(GetExifTagValueOrNull(ExifTag.SubsecTimeDigitized));
            ret.SubsecTimeOriginal = ParseExifDateTimeOrNull(GetExifTagValueOrNull(ExifTag.SubsecTimeOriginal));
            ret.OffsetTime = GetExifTagValueOrNull(ExifTag.OffsetTime).NullIfEmpty();
            ret.OffsetTimeDigitized = GetExifTagValueOrNull(ExifTag.OffsetTimeDigitized).NullIfEmpty();
            ret.OffsetTimeOriginal = GetExifTagValueOrNull(ExifTag.OffsetTimeOriginal).NullIfEmpty();
            ret.GpsImgDirection = GetExifTagValueOrNull2(ExifTag.GPSImgDirection)?.ToSingle();
            ret.GpsImgDirectionRef = GetExifTagValueOrNull(ExifTag.GPSImgDirectionRef).NullIfEmpty();
            ret.TagNames = exif.Values.Select(i => new ImageMetadata.Exif.TagName {Name = i.Tag.ToString()});
        }
        return ret;
    }

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
