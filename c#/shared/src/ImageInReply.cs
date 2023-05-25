using System.ComponentModel.DataAnnotations;

namespace tbm.Shared;

public class ImageInReply
{
    [Key] public uint ImageId { get; set; }
    public string UrlFilename { get; set; } = "";
    public uint ExpectedByteSize { get; set; }
}
