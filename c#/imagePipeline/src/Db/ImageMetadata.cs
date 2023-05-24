using System.Text.Json;
using SixLabors.ImageSharp.Formats.Bmp;
using SixLabors.ImageSharp.Formats.Gif;
using SixLabors.ImageSharp.Formats.Jpeg;
using SixLabors.ImageSharp.Formats.Png;
// ReSharper disable UnusedAutoPropertyAccessor.Global

namespace tbm.ImagePipeline.Db;

public class ImageMetadata : ImageMetadata.IImageMetadata
{
    public interface IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
    }

    [Key] public uint ImageId { get; set; }
    public string? Format { get; set; }
    public ushort Width { get; set; }
    public ushort Height { get; set; }
    public ushort BitsPerPixel { get; set; }
    public uint FrameCount { get; set; }
    public Other? EmbeddedOther { get; set; }
    public Exif? EmbeddedExif { get; set; }
    public Icc? EmbeddedIcc { get; set; }
    public Jpg? JpgMetadata { get; set; }
    public Png? PngMetadata { get; set; }
    public Gif? GifMetadata { get; set; }
    public Bmp? BmpMetadata { get; set; }
    public ByteSize? DownloadedByteSize { get; set; }
    public ulong XxHash3 { get; set; }

    public class ByteSize : IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
        public uint DownloadedByteSize { get; set; }
    }

    public class Other : IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
        public byte[]? Iptc { get; set; }
        public byte[]? Xmp { get; set; }
    }

    public class Exif : IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
        public string? Orientation { get; set; }
        public string? Make { get; set; }
        public string? Model { get; set; }
        public string? CreateDate { get; set; }
        public string? ModifyDate { get; set; }
        public required string TagNames { get; set; }
        public required byte[] RawBytes { get; set; }

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

    public class Icc : IImageMetadata
    {
        [Key] public uint ImageId { get; set; }
        public ulong XxHash3 { get; set; }
        public required byte[] RawBytes { get; set; }
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
