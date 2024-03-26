namespace tbm.Crawler.Db.Revision;

public abstract class RevisionWithSplitting<TBaseRevision> : IRevision
    where TBaseRevision : class, IRevision
{
    public uint TakenAt { get; set; }
    public ushort? NullFieldsBitMask { get; set; }
    public Dictionary<Type, TBaseRevision> SplitEntities { get; } = [];

    public virtual bool IsAllFieldsIsNullExceptSplit() => throw new NotImplementedException();

    protected TValue? GetSplitEntityValue<TSplitEntity, TValue>
        (Func<TSplitEntity, TValue?> valueSelector)
        where TSplitEntity : class, TBaseRevision =>
        SplitEntities.TryGetValue(typeof(TSplitEntity), out var entity)
            ? valueSelector((TSplitEntity)entity)
            : default;

    protected void SetSplitEntityValue<TSplitEntity, TValue>
        (TValue? value, Action<TSplitEntity, TValue?> valueSetter, Func<TSplitEntity> entityFactory)
        where TSplitEntity : class, TBaseRevision
    {
        if (SplitEntities.TryGetValue(typeof(TSplitEntity), out var entity))
            valueSetter((TSplitEntity)entity, value);
        else
            SplitEntities[typeof(TSplitEntity)] = entityFactory();
    }

    public class ModelBuilderExtension(ModelBuilder builder, string baseTableName)
    {
        public void HasKey<TRevision>(Expression<Func<TRevision, object?>> keySelector)
            where TRevision : class, TBaseRevision =>
            builder.Entity<TRevision>().ToTable(baseTableName).HasKey(keySelector);

        public void SplittingHasKeyAndName<TRevisionWithSplitting>
            (string tableNameSuffix, Expression<Func<TRevisionWithSplitting, object?>> keySelector)
            where TRevisionWithSplitting : RevisionWithSplitting<TBaseRevision> =>
            builder.Entity<TRevisionWithSplitting>().Ignore(e => e.NullFieldsBitMask)
                .ToTable($"{baseTableName}_{tableNameSuffix}").HasKey(keySelector);
    }
}
