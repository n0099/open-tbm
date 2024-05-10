using System.ComponentModel.DataAnnotations;

namespace tbm.Shared.Db;

public abstract class RowVersionedEntity
{
    [Timestamp] public uint Version { get; set; }
}
