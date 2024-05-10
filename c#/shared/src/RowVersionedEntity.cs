using System.ComponentModel.DataAnnotations;

namespace tbm.Shared;

public abstract class RowVersionedEntity
{
    [Timestamp] public uint Version { get; set; }
}
