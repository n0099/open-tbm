using Microsoft.EntityFrameworkCore.ChangeTracking;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public abstract class SaverWithRevision<TBaseRevision>
    (ILogger<SaverWithRevision<TBaseRevision>> logger)
    : IRevisionProperties
    where TBaseRevision : BaseRevisionWithSplitting
{
    protected delegate void AddRevisionDelegate(CrawlerDbContext db, IEnumerable<TBaseRevision> revision);
    protected abstract IReadOnlyDictionary<Type, AddRevisionDelegate> AddRevisionDelegatesKeyBySplitEntityType { get; }
    protected abstract NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName);

    protected virtual bool FieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => false;
    protected virtual bool FieldRevisionIgnorance(string propName, object? oldValue, object? newValue) => false;
    protected virtual bool UserFieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => false;
    protected virtual bool UserFieldRevisionIgnorance(string propName, object? oldValue, object? newValue) => false;

    protected void SaveEntitiesWithRevision<TEntity, TRevision>(
        CrawlerDbContext db,
        Func<TEntity, TRevision> revisionFactory,
        ILookup<bool, TEntity> existingOrNewLookup,
        Func<TEntity, TEntity> existingSelector)
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
            var whichPostType = typeof(TEntity);
            var entryIsUser = whichPostType == typeof(User);
            foreach (var p in entityEntry.Properties)
            {
                var pName = p.Metadata.Name;
                if (!p.IsModified || IsTimestampingFieldName(pName)) continue;

                if (FieldUpdateIgnorance(
                        pName, p.OriginalValue, p.CurrentValue)
                    || (entryIsUser && UserFieldUpdateIgnorance(
                        pName, p.OriginalValue, p.CurrentValue))

                    // possible rarely respond with the protoBuf default value 0
                    || (pName == nameof(BasePost.AuthorUid)
                        && p is { CurrentValue: 0L, OriginalValue: not null}))
                {
                    p.IsModified = false;
                    continue; // skip following revision check
                }
                if (FieldRevisionIgnorance(
                        pName, p.OriginalValue, p.CurrentValue) 
                    || (entryIsUser && UserFieldRevisionIgnorance(
                        pName, p.OriginalValue, p.CurrentValue)))
                    continue;

                if (IsLatestReplierUser(pName, p, entityEntry)) return null;

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

    private static bool IsLatestReplierUser(string pName, PropertyEntry p, EntityEntry entry)
    {
        // ThreadCrawlFacade.ParseLatestRepliers() will save users with empty string as portrait
        // they may soon be updated by (sub) reply crawler after it find out the latest reply
        // so we should ignore its revision update for all fields
        // ignore entire record is not possible via IFieldChangeIgnorance.GlobalFieldChangeIgnorance.Revision()
        // since it can only determine one field at the time
        if (pName != nameof(User.Portrait) || p.OriginalValue is not "") return false;

        // invokes OriginalValues.ToObject() to get a new instance
        // since entityInTracking is reference to the changed one
        var user = (User)entry.OriginalValues.ToObject();

        // create another user instance with only fields of latest replier filled
        var latestReplier = User.CreateLatestReplier(user.Uid, user.Name, user.DisplayName);

        // if they are same by fields values, the original one is the latest replier
        // that previously generated by ParseLatestRepliers()
        return new User.EqualityComparer().Equals(user, latestReplier);
    }
}
