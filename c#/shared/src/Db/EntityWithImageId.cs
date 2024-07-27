// ReSharper disable PropertyCanBeMadeInitOnly.Global
using System.ComponentModel.DataAnnotations;

namespace tbm.Shared.Db;

public abstract class EntityWithImageId : RowVersionedEntity
{
    public virtual uint ImageId { get; set; }

    public abstract class AsKey : EntityWithImageId
    {
        [Key] public override uint ImageId { get; set; }
    }
}
