// ReSharper disable PropertyCanBeMadeInitOnly.Global
namespace tbm.ImagePipeline.Db;

public class ForumScript : RowVersionedEntity
{
    public uint Fid { get; set; }
    public required string Script { get; set; }
}
