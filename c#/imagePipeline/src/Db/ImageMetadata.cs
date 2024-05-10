// ReSharper disable UnusedAutoPropertyAccessor.Global
// ReSharper disable PropertyCanBeMadeInitOnly.Global
// ReSharper disable UnusedMember.Global
// ReSharper disable UnusedMemberInSuper.Global
using System.ComponentModel;
using SixLabors.ImageSharp.PixelFormats;
using Point = NetTopologySuite.Geometries.Point;
using EntityWithImageIdAsKey = tbm.Shared.Db.EntityWithImageId.AsKey;

namespace tbm.ImagePipeline.Db;

public class ImageMetadata : EntityWithImageIdAsKey
{
    public string? Format { get; set; }
    public ushort Width { get; set; }
    public ushort Height { get; set; }
    public ushort BitsPerPixel { get; set; }
    public uint FrameCount { get; set; }
    public required byte[] XxHash3 { get; set; }
    public ByteSize? DownloadedByteSize { get; set; }
    public Exif? EmbeddedExif { get; set; }
    public Icc? EmbeddedIcc { get; set; }
    public Iptc? EmbeddedIptc { get; set; }
    public Xmp? EmbeddedXmp { get; set; }

    // new CICP profile for PNG in 3.1.0: https://github.com/SixLabors/ImageSharp/pull/2592
    public Jpg? JpgMetadata { get; set; }
    public Png? PngMetadata { get; set; }
    public Gif? GifMetadata { get; set; }
    public Bmp? BmpMetadata { get; set; }

    public class ByteSize : EntityWithImageIdAsKey
    {
        [SuppressMessage("Naming", "AV1710:Member name includes the name of its containing type")]
        public uint DownloadedByteSize { get; set; }
    }

    public abstract class Embedded : EntityWithImageIdAsKey
    {
        public byte[] XxHash3 { get; set; } = null!;
        public byte[]? RawBytes { get; set; }
    }

    public class Exif : Embedded
    {
        [SuppressMessage("ApiDesign", "SS039:An enum should specify a default value")]
        public enum ExifOrientation
        { // https://magnushoff.com/articles/jpeg-orientation/
            Horizontal = 1,
            MirrorHorizontal = 2,
            Rotate180 = 3,
            MirrorVertical = 4,
            MirrorHorizontalRotate270Cw = 5,
            Rotate90Cw = 6,
            MirrorHorizontalRotate90Cw = 7,
            Rotate270Cw = 8
        }

        public string? Orientation { get; set; }
        public string? ImageDescription { get; set; }
        public string? UserComment { get; set; }
        public string? Artist { get; set; }
        public string? XpAuthor { get; set; }
        public string? Copyright { get; set; }
        public string? ImageUniqueId { get; set; }
        public string? BodySerialNumber { get; set; }
        public string? Make { get; set; }
        public string? Model { get; set; }
        public string? Software { get; set; }
        public ushort? CustomRendered { get; set; }
        public DateTime? DateTime { get; set; }
        public string? DateTimeOffset { get; set; }
        public DateTime? DateTimeDigitized { get; set; }
        public string? DateTimeDigitizedOffset { get; set; }
        public DateTime? DateTimeOriginal { get; set; }
        public string? DateTimeOriginalOffset { get; set; }
        public string? OffsetTime { get; set; }
        public string? OffsetTimeDigitized { get; set; }
        public string? OffsetTimeOriginal { get; set; }
        public DateTime? GpsDateTime { get; set; }
        public Point? GpsCoordinate { get; set; }
        public float? GpsImgDirection { get; set; }
        public string? GpsImgDirectionRef { get; set; }

        // workaround to work with MetadataConsumer.CreateEmbeddedFromProfile()
        // https://stackoverflow.com/questions/75266722/type-cannot-satisfy-the-new-constraint-on-parameter-tparam-because-type
        public IEnumerable<TagName> TagNames { get; set; } = [];

        public class TagName : EntityWithImageId
        {
            public required string Name { get; set; }
        }
    }

    public class Icc : Embedded;
    public class Iptc : Embedded;
    public class Xmp : Embedded;

    public class Jpg : EntityWithImageIdAsKey
    {
        public int Quality { get; set; }
        public string? ColorType { get; set; }
        public bool? Interleaved { get; set; }
        public bool? Progressive { get; set; }

        public static Jpg? FromImageSharpMetadata(SixLabors.ImageSharp.Metadata.ImageMetadata meta)
        {
            if (meta.DecodedImageFormat is not JpegFormat) return null;
            var other = meta.GetJpegMetadata();
            return new()
            {
                Quality = other.Quality,
                ColorType = other.ColorType == null ? null : Enum.GetName(other.ColorType.Value),
                Interleaved = other.Interleaved,
                Progressive = other.Progressive
            };
        }
    }

    public class Png : EntityWithImageIdAsKey
    {
        public string? BitDepth { get; set; }
        public string? ColorType { get; set; }
        public string? InterlaceMethod { get; set; }
        public float Gamma { get; set; }
        public byte? TransparentR { get; set; }
        public byte? TransparentG { get; set; }
        public byte? TransparentB { get; set; }
        public string? TextData { get; set; }

        // new prop ColorTable in 3.1.0: https://github.com/SixLabors/ImageSharp/pull/2485
        // new prop RepeatCount for APNG in 3.1.0: https://github.com/SixLabors/ImageSharp/pull/2511
        public static Png? FromImageSharpMetadata(SixLabors.ImageSharp.Metadata.ImageMetadata meta)
        {
            if (meta.DecodedImageFormat is not PngFormat) return null;
            var other = meta.GetPngMetadata();
            Rgba32? transparent = other.TransparentColor == null ? null : (Rgba32)other.TransparentColor;
            return new()
            {
                BitDepth = other.BitDepth == null ? null : Enum.GetName(other.BitDepth.Value),
                ColorType = other.ColorType == null ? null : Enum.GetName(other.ColorType.Value),
                InterlaceMethod = other.InterlaceMethod == null ? null : Enum.GetName(other.InterlaceMethod.Value),
                Gamma = other.Gamma,
                TransparentR = transparent?.R,
                TransparentG = transparent?.G,
                TransparentB = transparent?.B,
                TextData = other.TextData.Any() ? JsonSerializer.Serialize(other.TextData) : null
            };
        }
    }

    public class Gif : EntityWithImageIdAsKey
    {
        public ushort RepeatCount { get; set; }
        public required string ColorTableMode { get; set; }
        public int GlobalColorTableLength { get; set; }

        // new prop BackgroundColorIndex in 3.1.0: https://github.com/SixLabors/ImageSharp/pull/2455#discussion_r1299208062
        public string? Comments { get; set; }

        public static Gif? FromImageSharpMetadata(SixLabors.ImageSharp.Metadata.ImageMetadata meta)
        {
            if (meta.DecodedImageFormat is not GifFormat) return null;
            var other = meta.GetGifMetadata();
            return new()
            {
                RepeatCount = other.RepeatCount,
                ColorTableMode = Enum.GetName(other.ColorTableMode) ?? throw new InvalidEnumArgumentException(
                    nameof(other.ColorTableMode), (int)other.ColorTableMode, other.ColorTableMode.GetType()),
                GlobalColorTableLength = other.GlobalColorTable?.Length ?? 0,
                Comments = other.Comments.Any() ? JsonSerializer.Serialize(other.Comments) : null
            };
        }
    }

    public class Bmp : EntityWithImageIdAsKey
    {
        public required string InfoHeaderType { get; set; }
        public required string BitsPerPixel { get; set; }

        public static Bmp? FromImageSharpMetadata(SixLabors.ImageSharp.Metadata.ImageMetadata meta)
        {
            if (meta.DecodedImageFormat is not BmpFormat) return null;
            var other = meta.GetBmpMetadata();
            return new()
            {
                InfoHeaderType = Enum.GetName(other.InfoHeaderType) ?? throw new InvalidEnumArgumentException(
                    nameof(other.InfoHeaderType), (int)other.InfoHeaderType, other.InfoHeaderType.GetType()),
                BitsPerPixel = Enum.GetName(other.BitsPerPixel) ?? throw new InvalidEnumArgumentException(
                    nameof(other.BitsPerPixel), (int)other.BitsPerPixel, other.BitsPerPixel.GetType())
            };
        }
    }
}
