// ReSharper disable PropertyCanBeMadeInitOnly.Global
using System.ComponentModel.DataAnnotations;

namespace tbm.Shared.Db;

public abstract class EntityWithImageId : RowVersionedEntity
{
    public uint ImageId { get; set; }

    public abstract class AsKey : EntityWithImageId
    {
        [Key] public new uint ImageId { get; set; }
    }
}
