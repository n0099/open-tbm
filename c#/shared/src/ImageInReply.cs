// ReSharper disable PropertyCanBeMadeInitOnly.Global
using System.ComponentModel.DataAnnotations;

namespace tbm.Shared;

public class ImageInReply : RowVersionedEntity
{
    [Key] public uint ImageId { get; set; }
    public required string UrlFilename { get; set; }
    public uint ExpectedByteSize { get; set; }
    public bool MetadataConsumed { get; set; }
    public bool HashConsumed { get; set; }
    public bool QrCodeConsumed { get; set; }
    public bool OcrConsumed { get; set; }
}
