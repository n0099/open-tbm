using System.ComponentModel.DataAnnotations.Schema;

namespace tbm.ImagePipeline.Db;

public class ImageFailed
{
    [Key] [DatabaseGenerated(DatabaseGeneratedOption.Identity)]
    public uint Id { get; set; }
    public uint ImageId { get; set; }
    public required string Exception { get; set; }
}
