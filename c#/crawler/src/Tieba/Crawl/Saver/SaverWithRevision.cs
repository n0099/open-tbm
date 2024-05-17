using Microsoft.EntityFrameworkCore.ChangeTracking;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public abstract partial class SaverWithRevision<TBaseRevision>(
    ILogger<SaverWithRevision<TBaseRevision>> logger)
    : IRevisionProperties
    where TBaseRevision : BaseRevisionWithSplitting
{
    protected delegate void AddRevisionDelegate(CrawlerDbContext db, IEnumerable<TBaseRevision> revision);
    protected abstract IReadOnlyDictionary<Type, AddRevisionDelegate> AddRevisionDelegatesKeyBySplitEntityType { get; }
    protected abstract NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName);

    protected virtual bool ShouldIgnoreEntityRevision(string propName, PropertyEntry propEntry, EntityEntry entityEntry) => false;
    protected virtual bool FieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => false;
    protected virtual bool FieldRevisionIgnorance(string propName, object? oldValue, object? newValue) => false;
    private static bool GlobalFieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => propName switch
    { // possible rarely respond with the protoBuf default value 0
        nameof(BasePost.AuthorUid) when newValue is 0L && oldValue is not null => true,
        _ => false
    };
}
public abstract partial class SaverWithRevision<TBaseRevision>
{
    protected void SaveEntitiesWithRevision<TEntity, TRevision>(
        CrawlerDbContext db,
        Func<TEntity, TRevision> revisionFactory,
        ILookup<bool, TEntity> existingOrNewLookup,
        Func<TEntity, TEntity> existingSelector,
        UserSaver.FieldChangeIgnorance? userFieldUpdateIgnorance = null,
        UserSaver.FieldChangeIgnorance? userFieldRevisionIgnorance = null)
        where TEntity : class
        where TRevision : BaseRevisionWithSplitting
    {
        db.Set<TEntity>().AddRange(existingOrNewLookup[false]); // newly added
        var newRevisions = existingOrNewLookup[true].Select(newEntity =>
        {
            var entityInTracking = existingSelector(newEntity);
            var entityEntry = db.Entry(entityInTracking);

            // this will mutate existingEntity which is referenced by entry
            entityEntry.CurrentValues.SetValues(newEntity);

            bool IsTimestampingFieldName(string name) => name is nameof(BasePost.LastSeenAt)
                or nameof(TimestampedEntity.CreatedAt) or nameof(TimestampedEntity.UpdatedAt);

            // rollback changes that overwrite original values with the default value 0 or null
            // for all fields of TimestampedEntity and BasePost.LastSeenAt
            // this will also affect the entity instance which entityInTracking references to it
            entityEntry.Properties
                .Where(prop => prop.IsModified && IsTimestampingFieldName(prop.Metadata.Name))
                .ForEach(prop => prop.IsModified = false);

            var revision = default(TRevision);
            var revisionNullFieldsBitMask = 0;
            var entityIsUser = typeof(TEntity) == typeof(User);
            foreach (var p in entityEntry.Properties)
            {
                var pName = p.Metadata.Name;
                if (!p.IsModified || IsTimestampingFieldName(pName)) continue;
                if (ShouldIgnoreEntityRevision(pName, p, entityEntry)) return null;

                if (FieldUpdateIgnorance(
                        pName, p.OriginalValue, p.CurrentValue)
                    || GlobalFieldUpdateIgnorance(
                        pName, p.OriginalValue, p.CurrentValue)
                    || (entityIsUser && userFieldUpdateIgnorance!(
                        pName, p.OriginalValue, p.CurrentValue)))
                {
                    p.IsModified = false;
                    continue; // skip following revision check
                }
                if (FieldRevisionIgnorance(
                        pName, p.OriginalValue, p.CurrentValue)
                    || (entityIsUser && userFieldRevisionIgnorance!(
                        pName, p.OriginalValue, p.CurrentValue)))
                    continue;

                if (!IRevisionProperties.Cache[typeof(TRevision)].TryGetValue(pName, out var revisionProp))
                {
                    object? ToHexWhenByteArray(object? value) =>
                        value is byte[] bytes ? $"0x{Convert.ToHexString(bytes).ToLowerInvariant()}" : value;
                    logger.LogWarning("Updating field {} is not existing in revision table, " +
                                       "newValue={}, oldValue={}, newObject={}, oldObject={}",
                        pName, ToHexWhenByteArray(p.CurrentValue), ToHexWhenByteArray(p.OriginalValue),
                        SharedHelper.UnescapedJsonSerialize(newEntity),
                        SharedHelper.UnescapedJsonSerialize(entityEntry.OriginalValues.ToObject()));
                }
                else
                {
                    revision ??= revisionFactory(entityInTracking);

                    // quote from MSDN https://learn.microsoft.com/en-us/dotnet/api/system.reflection.propertyinfo.setvalue
                    // If the property type of this PropertyInfo object is a value type and value is null
                    // the property will be set to the default value for that type.
                    // https://stackoverflow.com/questions/3049477/propertyinfo-setvalue-and-nulls
                    // this is a desired behavior to convert null values produced by ExtensionMethods.NullIfZero()
                    // back to zeros for some revision fields that had been entity splitting
                    // these split tables will only contain two Superkeys:
                    // the Candidate/Primary Key and the field gets split out
                    // so it's no longer necessary to use NullFieldsBitMasks to identify between
                    // the real null values and unchanged fields that have null as a placeholder
                    revisionProp.SetValue(revision, p.OriginalValue);

                    if (p.OriginalValue != null) continue;

                    // fields that have already split out will not exist in GetRevisionNullFieldBitMask
                    var whichBitToMask = GetRevisionNullFieldBitMask(pName);
                    revisionNullFieldsBitMask |= whichBitToMask; // mask the corresponding field bit with 1
                }
            }
            if (revision != null)
                revision.NullFieldsBitMask = (NullFieldsBitMask?)revisionNullFieldsBitMask.NullIfZero();
            return revision;
        }).OfType<TRevision>().ToList();
        if (newRevisions.Count == 0) return; // quick exit to prevent execute sql with WHERE FALSE clause

        db.Set<TRevision>().AddRange(
            newRevisions.Where(rev => !rev.IsAllFieldsIsNullExceptSplit()));
        newRevisions.OfType<RevisionWithSplitting<TBaseRevision>>()
            .SelectMany(rev => rev.SplitEntities)
            .GroupBy(pair => pair.Key, pair => pair.Value)
            .ForEach(g => AddRevisionDelegatesKeyBySplitEntityType[g.Key](db, g));
    }
}
