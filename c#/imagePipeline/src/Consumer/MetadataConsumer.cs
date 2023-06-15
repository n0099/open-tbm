using System.Diagnostics.CodeAnalysis;
using System.Globalization;
using System.IO.Hashing;
using System.Text.RegularExpressions;
using NetTopologySuite.Geometries;
using SixLabors.ImageSharp.Metadata.Profiles.Exif;
using SixLabors.ImageSharp.Metadata.Profiles.Icc;
using SixLabors.ImageSharp.Metadata.Profiles.Iptc;
using SixLabors.ImageSharp.Metadata.Profiles.Xmp;
using Point = NetTopologySuite.Geometries.Point;

namespace tbm.ImagePipeline.Consumer;

public partial class MetadataConsumer
{
    private readonly (ulong[] Exif, ulong[] Icc, ulong[] Iptc, ulong[] Xmp) _commonEmbeddedMetadataXxHash3ToIgnore;

    public MetadataConsumer(IConfiguration config)
    {
        var section = config.GetSection("MetadataConsumer").GetSection("CommonEmbeddedMetadataXxHash3ToIgnore");
        ulong[] GetCommonXxHash3ToIgnore(string key) => section.GetSection(key).Get<ulong[]>() ?? Array.Empty<ulong>();
        _commonEmbeddedMetadataXxHash3ToIgnore = (
            Exif: GetCommonXxHash3ToIgnore("Exif"),
            Icc: GetCommonXxHash3ToIgnore("Icc"),
            Iptc: GetCommonXxHash3ToIgnore("Iptc"),
            Xmp: GetCommonXxHash3ToIgnore("Xmp")
        );
    }

    static MetadataConsumer() => NetTopologySuite.NtsGeometryServices.Instance = new(
        coordinateSequenceFactory: NetTopologySuite.Geometries.Implementation.CoordinateArraySequenceFactory.Instance,
        precisionModel: new(1000d),
        srid: 4326, // WGS84
        geometryOverlay: GeometryOverlay.NG,
        coordinateEqualityComparer: new());

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

            var parsedDateTime = ExifDateTimeTagValuesParser.ParseExifDateTimeOrNull(
                GetExifTagValueOrNull(ExifTag.DateTime), GetExifTagValueOrNull(ExifTag.SubsecTime));
            ret.DateTime = parsedDateTime?.DateTime;
            ret.DateTimeOffset = parsedDateTime?.Offset;

            var parsedDateTimeDigitized = ExifDateTimeTagValuesParser.ParseExifDateTimeOrNull(
                GetExifTagValueOrNull(ExifTag.DateTimeDigitized), GetExifTagValueOrNull(ExifTag.SubsecTimeDigitized));
            ret.DateTimeDigitized = parsedDateTimeDigitized?.DateTime;
            ret.DateTimeDigitizedOffset = parsedDateTimeDigitized?.Offset;

            var parsedDateTimeOriginal = ExifDateTimeTagValuesParser.ParseExifDateTimeOrNull(
                GetExifTagValueOrNull(ExifTag.DateTimeOriginal), GetExifTagValueOrNull(ExifTag.SubsecTimeOriginal));
            ret.DateTimeOriginal = parsedDateTimeOriginal?.DateTime;
            ret.DateTimeOriginalOffset = parsedDateTimeOriginal?.Offset;

            ret.OffsetTime = GetExifTagValueOrNull(ExifTag.OffsetTime).NullIfEmpty();
            ret.OffsetTimeDigitized = GetExifTagValueOrNull(ExifTag.OffsetTimeDigitized).NullIfEmpty();
            ret.OffsetTimeOriginal = GetExifTagValueOrNull(ExifTag.OffsetTimeOriginal).NullIfEmpty();

            ret.GpsDateTime = ExifGpsTagValuesParser.ParseGpsDateTimeOrNull(
                GetExifTagValueOrNull(ExifTag.GPSTimestamp),
                GetExifTagValueOrNull(ExifTag.GPSDateStamp));
            ret.GpsCoordinate = ExifGpsTagValuesParser.ParseGpsCoordinateOrNull(
                GetExifTagValueOrNull(ExifTag.GPSLatitude),
                GetExifTagValueOrNull(ExifTag.GPSLatitudeRef),
                GetExifTagValueOrNull(ExifTag.GPSLongitude),
                GetExifTagValueOrNull(ExifTag.GPSLongitudeRef));
            ret.GpsImgDirection = GetExifTagValueOrNull2(ExifTag.GPSImgDirection)?.ToSingle();
            ret.GpsImgDirectionRef = GetExifTagValueOrNull(ExifTag.GPSImgDirectionRef).NullIfEmpty();

            ret.TagNames = exif.Values.Select(i => new ImageMetadata.Exif.TagName {Name = i.Tag.ToString()}).ToList();
        }
        return ret;
    }

    private class ExifGpsTagValuesParser
    {
        public static DateTime? ParseGpsDateTimeOrNull(Rational[]? timeStamp, string? dateStamp)
        {
            if (timeStamp == null || dateStamp == null) return null;

            var dateParts = dateStamp.Split(':').Select(int.Parse).ToList();
            if (dateParts.Count != 3) throw new ArgumentOutOfRangeException(nameof(dateStamp), dateStamp,
                "Unexpected GPSDateStamp, expecting three parts separated by \":\".");

            if (timeStamp.Length != 3) throw new ArgumentOutOfRangeException(nameof(timeStamp), timeStamp,
                "Unexpected GPSTimeStamp, expecting three rationals.");
            if (timeStamp.Any(i => i.Denominator != 1)) throw new ArgumentException(
                "Unexpected fraction number in parts of GPSTimeStamp, expecting integer as rationals.", nameof(timeStamp));
            var timeParts = timeStamp.Select(i => (int)i.ToDouble()).ToList();

            return new DateTime(year: dateParts[0], month: dateParts[1], day: dateParts[2],
                hour: timeParts[0], minute: timeParts[1], second: timeParts[2]);
        }

        public static Point? ParseGpsCoordinateOrNull
            (IEnumerable<Rational>? latitude, string? latitudeRef, IEnumerable<Rational>? longitude, string? longitudeRef)
        {
            var latitudeDms = latitude?.Select(i => i.ToDouble()).ToList();
            var longitudeDms = longitude?.Select(i => i.ToDouble()).ToList();
            if (latitudeDms == null || latitudeRef == null || longitudeDms == null || longitudeRef == null)
                return null;

            latitudeDms[0] = latitudeRef switch
            {
                "N" => latitudeDms[0],
                "S" => -latitudeDms[0],
                _ => throw new ArgumentOutOfRangeException(nameof(latitudeRef), latitudeRef,
                    "Unexpected GPSLatitudeRef, expecting \"N\" or \"S\".")
            };
            longitudeDms[0] = longitudeRef switch
            {
                "E" => longitudeDms[0],
                "W" => -longitudeDms[0],
                _ => throw new ArgumentOutOfRangeException(nameof(longitudeRef), longitudeRef,
                    "Unexpected GPSLongitudeRef, expecting \"E\" or \"W\".")
            };

            return NetTopologySuite.NtsGeometryServices.Instance.CreateGeometryFactory()
                .CreatePoint(new Coordinate(ConvertDmsToDd(longitudeDms), ConvertDmsToDd(latitudeDms)));
        }

        private static double ConvertDmsToDd(IReadOnlyList<double> dms)
        { // https://stackoverflow.com/questions/3249700/convert-degrees-minutes-seconds-to-decimal-coordinates
            if (dms.Count != 3) throw new ArgumentException(
                "Unexpected length for DMS, expecting three doubles.", nameof(dms));
            var degrees = dms[0];
            var minutes = dms[1];
            var seconds = dms[2];
            return degrees > 0
                ? degrees + (minutes / 60) + (seconds / 3600)
                : degrees - (minutes / 60) - (seconds / 3600);
        }
    }

    private partial class ExifDateTimeTagValuesParser
    {
        public record DateTimeAndOffset(DateTime DateTime, string? Offset);

        public static DateTimeAndOffset? ParseExifDateTimeOrNull(string? exifDateTime, string? exifFractionalSeconds)
        { // https://gist.github.com/thanatos/eee17100476a336a711e
            // tested inputs with valid results:
            // "2019:02:07 21:238"         => "2019/2/7 21:23:08"  , ""
            // "2019:04:26 20:08:02"       => "2019/4/26 20:08:02" , ""
            // "2019:04:26 24:08:02"       => "2019/4/27 0:08:02"  , ""
            // "2018:09:12 21:20:08下午"    => "2018/9/12 21:20:08" , ""
            // "2018:09:12 09:20:08上午"    => "2018/9/12 9:20:08"  , ""
            // "2018:09:12 09:20:08下午"    => "2018/9/12 21:20:08" , ""
            // "2018:09:12 9:20:08下午"     => "2018/9/12 21:20:08" , ""
            // "2013: 1: 1  8:10:25"       => "2013/1/1 8:10:25"   , ""
            // "2019-04-09T02:14:04-12:00" => "2019/4/9 2:14:04"   , "-12:00"
            // "2019-04-09T02:14:04+09:00" => "2019/4/9 2:14:04"   , "+09:00"
            // "2017/05/22 00:04:31"       => "2017/5/22 0:04:31"  , ""
            // "Sat Mar 03 10:05:45 2007"  => "2007/3/3 10:05:45"  , ""
            // "1556068385188"             => "2019/4/24 1:13:05"  , "+00:00"
            if (exifDateTime == null) return null;
            var hasFractionalSeconds = int.TryParse(exifFractionalSeconds, out var fractionalSeconds);
            fractionalSeconds = hasFractionalSeconds ? fractionalSeconds : 0;
            return ParseExifDateTimeWithoutOffset(exifDateTime, fractionalSeconds)
                   ?? ParseExifDateTimeWithOffset(exifDateTime, fractionalSeconds)
                   ?? ParseExifDateTimeWithHoursBeyond24(exifDateTime)
                   ?? ParseExifDateTimeAsUnixTimestampMilliseconds(exifDateTime)
                   ?? throw new ArgumentException(
                       $"Failed to parse provided EXIF date time {exifDateTime} with fractional seconds {fractionalSeconds}.");
        }

        private static DateTimeAndOffset? ParseExifDateTimeWithoutOffset(string exifDateTime, int fractionalSeconds)
        {
            var culture = (DateTimeFormatInfo)CultureInfo.InvariantCulture.DateTimeFormat.Clone();
            culture.AMDesignator = "上午";
            culture.PMDesignator = "下午";
            var dateTime = DateTime.TryParseExact(exifDateTime, new[]
            {
                "yyyy':'MM':'dd HH':'mm':'ss",   // 2019:04:26 20:08:02
                "yyyy':'MM':'dd HH':'mm':'sstt", // 2018:09:12 21:20:08下午, 2018:09:12 09:20:08上午
                "yyyy':'MM':'dd h':'mm':'sstt",  // 2018:09:12 09:20:08下午, 2018:09:12 9:20:08下午
                "yyyy':'MM':'dd HH':'mms",       // 2019:02:07 21:238
                "yyyy':'M':'d H':'m':'s",        // 2013: 1: 1  8:10:25 with DateTimeStyles.AllowWhiteSpaces
                "yyyy'/'MM'/'dd HH':'mm':'ss",   // 2017/05/22 00:04:31
                "ddd MMM dd HH':'mm':'ss yyyy"   // Sat Mar 03 10:05:45 2007
            }, culture, DateTimeStyles.AllowWhiteSpaces, out var dt)
                ? dt
                : default;
            if (dateTime == default) return null;
            if (fractionalSeconds != 0) dateTime = dateTime.AddSeconds(fractionalSeconds / 10d);
            return new(dateTime, Offset: null);
        }

        private static DateTimeAndOffset? ParseExifDateTimeWithOffset(string exifDateTime, int fractionalSeconds)
        {
            var dateTimeOffset = DateTimeOffset.TryParseExact(exifDateTime,
                "yyyy-MM-ddTHH':'mm':'sszzz" // 2019-04-09T02:14:04-12:00, 2019-04-09T02:14:04+09:00
                , CultureInfo.InvariantCulture, DateTimeStyles.AllowWhiteSpaces, out var dto)
                ? dto
                : default;
            if (dateTimeOffset == default) return null;
            if (fractionalSeconds != 0) dateTimeOffset = dateTimeOffset.AddSeconds(fractionalSeconds / 10d);
            return new(dateTimeOffset.DateTime, dateTimeOffset.ToString("zzz"));
        }

        [GeneratedRegex(
            "^(?<year>[0-9]{4}):(?<month>[0-9]{2}):(?<day>[0-9]{2}) (?<hour>[0-9]{2}):(?<minute>[0-9]{2}):(?<second>[0-9]{2})$",
            RegexOptions.Compiled, matchTimeoutMilliseconds: 100)]
        private static partial Regex ExtractExifDateTimePartsRegex();

        private static DateTimeAndOffset? ParseExifDateTimeWithHoursBeyond24(string exifDateTime)
        { // https://stackoverflow.com/questions/5208607/parsing-times-above-24-hours-in-c-sharp/76483705#76483705
            // https://en.wikipedia.org/wiki/Date_and_time_notation_in_Japan#Time
            // https://ja.wikipedia.org/wiki/30%E6%99%82%E9%96%93%E5%88%B6
            // e.g. 2019:04:26 24:08:02
            var match = ExtractExifDateTimePartsRegex().Match(exifDateTime);
            if (!match.Success) return null;
            return new(new DateTime(
                    int.Parse(match.Groups["year"].ValueSpan),
                    int.Parse(match.Groups["month"].ValueSpan),
                    int.Parse(match.Groups["day"].ValueSpan),
                    hour: 0,
                    int.Parse(match.Groups["minute"].ValueSpan),
                    int.Parse(match.Groups["second"].ValueSpan))
                .AddHours(int.Parse(match.Groups["hour"].ValueSpan)), Offset: null);
        }

        // accepting from 1973-03-03 17:46:40.000 to 2286-11-21 01:46:39.999 when the input contains no leading zeros
        [GeneratedRegex("^[0-9]{12,13}$", RegexOptions.Compiled, matchTimeoutMilliseconds: 100)]
        private static partial Regex ExtractUnixTimestampMillisecondsRegex();

        private static DateTimeAndOffset? ParseExifDateTimeAsUnixTimestampMilliseconds(string exifDateTime)
        { // e.g. 1556068385188
            var isMatch = ExtractUnixTimestampMillisecondsRegex().IsMatch(exifDateTime);
            if (!isMatch) return null;
            return new(DateTimeOffset.FromUnixTimeMilliseconds(long.Parse(exifDateTime)).DateTime, "+00:00");
        }
    }
}
