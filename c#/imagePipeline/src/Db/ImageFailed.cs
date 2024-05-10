// ReSharper disable PropertyCanBeMadeInitOnly.Global
using System.ComponentModel.DataAnnotations.Schema;

namespace tbm.ImagePipeline.Db;

public class ImageFailed : EntityWithImageId
{
    [Key] [DatabaseGenerated(DatabaseGeneratedOption.Identity)]
    public uint Id { get; set; }
    public required string Exception { get; set; }
}
