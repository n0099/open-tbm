// ReSharper disable UnusedAutoPropertyAccessor.Global
// ReSharper disable PropertyCanBeMadeInitOnly.Global
// ReSharper disable UnusedMember.Global
using Point = NetTopologySuite.Geometries.Point;

namespace tbm.ImagePipeline.Db;

public class ImageMetadata : ImageMetadata.IImageMetadata
{
    public interface IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
    }

    public interface IEmbedded : IImageMetadata
    {
        public ulong XxHash3 { get; set; }
        public byte[]? RawBytes { get; set; }
    }

    [Key] public uint ImageId { get; set; }
    public string? Format { get; set; }
    public ushort Width { get; set; }
    public ushort Height { get; set; }
    public ushort BitsPerPixel { get; set; }
    public uint FrameCount { get; set; }
    public ulong XxHash3 { get; set; }
    public ByteSize? DownloadedByteSize { get; set; }
    public Exif? EmbeddedExif { get; set; }
    public Icc? EmbeddedIcc { get; set; }
    public Iptc? EmbeddedIptc { get; set; }
    public Xmp? EmbeddedXmp { get; set; }
    public Jpg? JpgMetadata { get; set; }
    public Png? PngMetadata { get; set; }
    public Gif? GifMetadata { get; set; }
    public Bmp? BmpMetadata { get; set; }

    public class ByteSize : IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
        public uint DownloadedByteSize { get; set; }
    }

    public class Exif : IEmbedded
    {
        [Key] public uint ImageId { get; set; }
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
        public ulong XxHash3 { get; set; }
        public byte[]? RawBytes { get; set; }

        // workaround to work with MetadataConsumer.CreateEmbeddedFromProfile()
        // https://stackoverflow.com/questions/75266722/type-cannot-satisfy-the-new-constraint-on-parameter-tparam-because-type
        public ICollection<TagName> TagNames { get; set; } = new List<TagName>();

        public class TagName : IImageMetadata
        {
            public uint ImageId { get; set; }
            public required string Name { get; set; }
        }

        public enum ExifOrientation
        { // https://magnushoff.com/articles/jpeg-orientation/
            Horizontal = 1,
            MirrorHorizontal = 2,
            Rotate180 = 3,
            MirrorVertical = 4,
            MirrorHorizontalRotate270Cw = 5,
            Rotate90Cw = 6,
            MirrorHorizontalRotate90Cw = 7,
            Rotate270Cw = 8,
        }
    }

    public class Icc : IEmbedded
    {
        [Key] public uint ImageId { get; set; }
        public ulong XxHash3 { get; set; }
        public byte[]? RawBytes { get; set; }
    }

    public class Iptc : IEmbedded
    {
        [Key] public uint ImageId { get; set; }
        public ulong XxHash3 { get; set; }
        public byte[]? RawBytes { get; set; }
    }

    public class Xmp : IEmbedded
    {
        [Key] public uint ImageId { get; set; }
        public ulong XxHash3 { get; set; }
        public byte[]? RawBytes { get; set; }
    }

    public class Jpg : IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
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

    public class Png : IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
        public string? BitDepth { get; set; }
        public string? ColorType { get; set; }
        public string? InterlaceMethod { get; set; }
        public float Gamma { get; set; }
        public byte? TransparentR { get; set; }
        public byte? TransparentG { get; set; }
        public byte? TransparentB { get; set; }
        public byte? TransparentL { get; set; }
        public bool HasTransparency { get; set; }
        public string? TextData { get; set; }

        public static Png? FromImageSharpMetadata(SixLabors.ImageSharp.Metadata.ImageMetadata meta)
        {
            if (meta.DecodedImageFormat is not PngFormat) return null;
            var other = meta.GetPngMetadata();
            return new()
            {
                BitDepth = other.BitDepth == null ? null : Enum.GetName(other.BitDepth.Value),
                ColorType = other.ColorType == null ? null : Enum.GetName(other.ColorType.Value),
                InterlaceMethod = other.InterlaceMethod == null ? null : Enum.GetName(other.InterlaceMethod.Value),
                Gamma = other.Gamma,
                TransparentR = other.TransparentRgb24?.R,
                TransparentG = other.TransparentRgb24?.G,
                TransparentB = other.TransparentRgb24?.B,
                TransparentL = other.TransparentL8?.PackedValue,
                HasTransparency = other.HasTransparency,
                TextData = other.TextData.Any() ? JsonSerializer.Serialize(other.TextData) : null
            };
        }
    }

    public class Gif : IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
        public ushort RepeatCount { get; set; }
        public required string ColorTableMode { get; set; }
        public int GlobalColorTableLength { get; set; }
        public string? Comments { get; set; }

        public static Gif? FromImageSharpMetadata(SixLabors.ImageSharp.Metadata.ImageMetadata meta)
        {
            if (meta.DecodedImageFormat is not GifFormat) return null;
            var other = meta.GetGifMetadata();
            return new()
            {
                RepeatCount = other.RepeatCount,
                ColorTableMode = Enum.GetName(other.ColorTableMode) ?? throw new IndexOutOfRangeException(),
                GlobalColorTableLength = other.GlobalColorTableLength,
                Comments = other.Comments.Any() ? JsonSerializer.Serialize(other.Comments) : null
            };
        }
    }

    public class Bmp : IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
        public required string InfoHeaderType { get; set; }
        public required string BitsPerPixel { get; set; }

        public static Bmp? FromImageSharpMetadata(SixLabors.ImageSharp.Metadata.ImageMetadata meta)
        {
            if (meta.DecodedImageFormat is not BmpFormat) return null;
            var other = meta.GetBmpMetadata();
            return new()
            {
                InfoHeaderType = Enum.GetName(other.InfoHeaderType) ?? throw new IndexOutOfRangeException(),
                BitsPerPixel = Enum.GetName(other.BitsPerPixel) ?? throw new IndexOutOfRangeException()
            };
        }
    }
}
