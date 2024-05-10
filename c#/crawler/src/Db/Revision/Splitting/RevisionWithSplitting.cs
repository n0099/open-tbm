namespace tbm.Crawler.Db.Revision.Splitting;

public abstract class RevisionWithSplitting<TBaseRevision> : BaseRevisionWithSplitting
    where TBaseRevision : BaseRevisionWithSplitting
{
    private readonly Dictionary<Type, TBaseRevision> _splitEntities = [];
    public IReadOnlyDictionary<Type, TBaseRevision> SplitEntities => _splitEntities;
    public override bool IsAllFieldsIsNullExceptSplit() => throw new NotSupportedException();

    protected TValue? GetSplitEntityValue<TSplitEntity, TValue>
        (Func<TSplitEntity, TValue?> valueSelector)
        where TSplitEntity : class, TBaseRevision =>
        _splitEntities.TryGetValue(typeof(TSplitEntity), out var entity)
            ? valueSelector((TSplitEntity)entity)
            : default;

    protected void SetSplitEntityValue<TSplitEntity, TValue>
        (TValue? value, Action<TSplitEntity, TValue?> valueSetter, Func<TSplitEntity> entityFactory)
        where TSplitEntity : class, TBaseRevision
    {
        if (_splitEntities.TryGetValue(typeof(TSplitEntity), out var entity))
            valueSetter((TSplitEntity)entity, value);
        else
            _splitEntities[typeof(TSplitEntity)] = entityFactory();
    }

    public class ModelBuilderExtension(ModelBuilder builder, string baseTableName)
    {
        public void HasKey<TRevision>(Expression<Func<TRevision, object?>> keySelector)
            where TRevision : class, TBaseRevision =>
            builder.Entity<TRevision>().ToTable(baseTableName).HasKey(keySelector);

        public void SplittingHasKey<TRevisionWithSplitting>
            (string tableNameSuffix, Expression<Func<TRevisionWithSplitting, object?>> keySelector)
            where TRevisionWithSplitting : RevisionWithSplitting<TBaseRevision> =>
            builder.Entity<TRevisionWithSplitting>().Ignore(e => e.NullFieldsBitMask)
                .ToTable($"{baseTableName}_{tableNameSuffix}").HasKey(keySelector);
    }
}
