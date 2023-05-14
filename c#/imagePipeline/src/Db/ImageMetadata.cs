using SixLabors.ImageSharp.Formats.Jpeg;

namespace tbm.ImagePipeline.Db;

public class ImageMetadata
{
    [Key] public uint ImageId { get; set; }
    public string? Format { get; set; }
    public ushort Width { get; set; }
    public ushort Height { get; set; }
    public ushort BitsPerPixel { get; set; }
    public ushort FrameCount { get; set; }
    public Embedded? EmbeddedMetadata { get; set; }
    public Jpg? JpgMetadata { get; set; }
    public uint ByteSize { get; set; }
    public ulong XxHash3 { get; set; }

    public class Embedded
    {
        [Key] public uint ImageId { get; set; }
        public byte[]? Exif { get; set; }
        public byte[]? Icc { get; set; }
        public byte[]? Iptc { get; set; }
        public byte[]? Xmp { get; set; }
    }

    public class Jpg
    {
        [Key] public uint ImageId { get; set; }
        public int Quality { get; set; }
        public string? ColorType { get; set; }
        public bool? Interleaved { get; set; }
        public bool? Progressive { get; set; }

        public static Jpg? FromImageSharpMetadata(SixLabors.ImageSharp.Metadata.ImageMetadata meta, ImageId imageId)
        {
            if (meta.DecodedImageFormat is not JpegFormat) return null;
            var other = meta.GetJpegMetadata();
            return new()
            {
                ImageId = imageId,
                Quality = other.Quality,
                ColorType = other.ColorType == null ? null : Enum.GetName(other.ColorType.Value),
                Interleaved = other.Interleaved,
                Progressive = other.Progressive
            };
        }
    }
}
