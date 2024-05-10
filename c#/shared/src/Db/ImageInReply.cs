// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.Shared.Db;

public class ImageInReply : EntityWithImageId.AsKey
{
    public required string UrlFilename { get; set; }
    public uint ExpectedByteSize { get; set; }
    public bool MetadataConsumed { get; set; }
    public bool HashConsumed { get; set; }
    public bool QrCodeConsumed { get; set; }
    public bool OcrConsumed { get; set; }
}
