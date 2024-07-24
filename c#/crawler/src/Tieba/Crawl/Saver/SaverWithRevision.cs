namespace tbm.Crawler.Tieba.Crawl.Saver;

public abstract partial class SaverWithRevision<TBaseRevision, TRevisionId>(
    ILogger<SaverWithRevision<TBaseRevision, TRevisionId>> logger)
    : IRevisionProperties
    where TBaseRevision : BaseRevisionWithSplitting
    where TRevisionId : struct
{
    protected delegate void AddSplitRevisionsDelegate(CrawlerDbContext db, IEnumerable<TBaseRevision> revisions);
    protected abstract Lazy<Dictionary<Type, AddSplitRevisionsDelegate>>
        AddSplitRevisionsDelegatesKeyByEntityType { get; }

    protected void AddRevisionsWithDuplicateIndex<TRevision>(CrawlerDbContext db, IEnumerable<TBaseRevision> revisions)
        where TRevision : TBaseRevision
    {
        var newRevisions = revisions.OfType<TRevision>().ToList();
        if (newRevisions.Count == 0) return; // quick exit to prevent execute sql with WHERE FALSE clause
        var dbSet = db.Set<TRevision>();
        var visitor = new ReplaceParameterTypeVisitor<TBaseRevision, TRevision>();

        var existingRevisions = dbSet.AsNoTracking()
            .WhereOrContainsValues(newRevisions, [
                newRevision => existingRevision => existingRevision.TakenAt == newRevision.TakenAt,
                newRevision => (Expression<Func<TRevision, bool>>)visitor.Visit(IsRevisionIdEqualsExpression(newRevision))
            ])
            .Cast<TBaseRevision>()
            .Select(RevisionIdWithDuplicateIndexProjectionFactory())
            .ToList();
        (from existingRevision in existingRevisions
                join newRevision in newRevisions
                    on existingRevision.RevisionId equals RevisionIdSelector(newRevision)
                select (existingRevision, newRevision))
            .ForEach(t =>
                t.newRevision.DuplicateIndex = (ushort)(t.existingRevision.DuplicateIndex + 1));
        dbSet.AddRange(newRevisions);
    }
}
public partial class SaverWithRevision<TBaseRevision, TRevisionId>
{
    protected abstract TRevisionId RevisionIdSelector(TBaseRevision entity);
    protected abstract Expression<Func<TBaseRevision, bool>>
        IsRevisionIdEqualsExpression(TBaseRevision newRevision);

    protected abstract Expression<Func<TBaseRevision, RevisionIdWithDuplicateIndexProjection>>
        RevisionIdWithDuplicateIndexProjectionFactory();
    [SuppressMessage("ReSharper", "PropertyCanBeMadeInitOnly.Global")]
    protected class RevisionIdWithDuplicateIndexProjection
    {
        public TRevisionId RevisionId { get; set; }
        public ushort DuplicateIndex { get; set; }
    }
}
public partial class SaverWithRevision<TBaseRevision, TRevisionId>
{
    protected virtual bool FieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => false;
    protected virtual bool FieldRevisionIgnorance(string propName, object? oldValue, object? newValue) => false;
    private static bool GlobalFieldUpdateIgnorance(string propName, object? oldValue, object? newValue) => propName switch
    { // possible rarely respond with the protoBuf default value 0
        nameof(BasePost.AuthorUid) when newValue is 0L && oldValue is not null => true,
        _ => false
    };
}
public partial class SaverWithRevision<TBaseRevision, TRevisionId>
{
    private static bool IsTimestampingFieldName(string name) => name is nameof(BasePost.LastSeenAt)
        or nameof(TimestampedEntity.CreatedAt) or nameof(TimestampedEntity.UpdatedAt);

    protected abstract NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName);

    protected record ExistingAndNewEntities<TEntity>
        (TEntity ExistingEntity, TEntity NewEntity) where TEntity : RowVersionedEntity;

    protected void SaveExistingEntities<TEntity>(
        CrawlerDbContext db,
        IEnumerable<ExistingAndNewEntities<TEntity>> existingAndNewEntities)
        where TEntity : RowVersionedEntity =>
        existingAndNewEntities.ForEach(existingAndNew =>
        {
            var (existingEntity, newEntity) = existingAndNew;
            var entityEntry = db.Entry(existingEntity);

            entityEntry.CurrentValues.SetValues(newEntity); // mutate existingEntity that referenced by entry
            entityEntry.Property(e => e.Version).IsModified = false; // newEntity.Version will always be default 0

            // https://stackoverflow.com/questions/66206459/update-navigation-property-with-entity-currentvalues-setvalues/66491805#66491805
            (from newNavigation in db.Entry(newEntity).Navigations
                    join existingNavigation in entityEntry.Navigations
                        on newNavigation.Metadata.Name equals existingNavigation.Metadata.Name
                    select (newNavigation, existingNavigation))
                .ForEach(t => t.existingNavigation.CurrentValue = t.newNavigation.CurrentValue);

            // rollback changes that overwrite original values with the default value 0 or null
            // for all fields of TimestampedEntity and BasePost.LastSeenAt
            // this will also affect the entity instance which entityInTracking references to it
            entityEntry.Properties
                .Where(prop => prop.IsModified && IsTimestampingFieldName(prop.Metadata.Name))
                .ForEach(prop => prop.IsModified = false);
        });

    protected void SaveExistingEntityRevisions<TEntity, TRevision>(
        CrawlerDbContext db,
        Func<TEntity, TRevision> revisionFactory,
        IEnumerable<ExistingAndNewEntities<TEntity>> existingAndNewEntities,
        UserSaver.FieldChangeIgnorance? userFieldUpdateIgnorance = null,
        UserSaver.FieldChangeIgnorance? userFieldRevisionIgnorance = null)
        where TEntity : RowVersionedEntity
        where TRevision : class, TBaseRevision
    {
        var newRevisions = existingAndNewEntities.Select(existingAndNew =>
        {
            var (existingEntity, newEntity) = existingAndNew;
            var entityEntry = db.Entry(existingEntity);
            var revision = default(TRevision);
            var revisionNullFieldsBitMask = 0;
            var entityIsUser = typeof(TEntity) == typeof(User);
            foreach (var p in entityEntry.Properties)
            {
                var pName = p.Metadata.Name;
                if (!p.IsModified || IsTimestampingFieldName(pName)) continue;

                // foreign key might be marked as modified after its reference navigation
                // gets replaced by another entity with the same PK in SaveExistingEntities()
                if (Equals(p.OriginalValue, p.CurrentValue)
                    || FieldUpdateIgnorance(
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
                        value is byte[] bytes ? bytes.ToHex() : value;
                    logger.LogWarning("Updating field {} is not existing in revision table, " +
                                       "newValue={}, oldValue={}, newObject={}, oldObject={}",
                        pName, ToHexWhenByteArray(p.CurrentValue), ToHexWhenByteArray(p.OriginalValue),
                        SharedHelper.UnescapedJsonSerialize(newEntity),
                        SharedHelper.UnescapedJsonSerialize(entityEntry.OriginalValues.ToObject()));
                }
                else
                {
                    revision ??= revisionFactory(existingEntity);

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

        AddRevisionsWithDuplicateIndex<TRevision>(db,
            newRevisions.Where(rev => !rev.IsAllFieldsIsNullExceptSplit()));
        newRevisions.OfType<RevisionWithSplitting<TBaseRevision>>()
            .SelectMany(rev => rev.SplitEntities)
            .GroupBy(pair => pair.Key, pair => pair.Value)
            .ForEach(g => AddSplitRevisionsDelegatesKeyByEntityType.Value[g.Key](db, g));
    }
}
