namespace tbm.Crawler.Db.Revision;

public abstract class RevisionWithSplitting<TBaseRevision> : IRevision
    where TBaseRevision : class, IRevision
{
    public uint TakenAt { get; set; }
    public ushort? NullFieldsBitMask { get; set; }
    public virtual bool IsAllFieldsIsNullExceptSplit() => throw new NotImplementedException();

    public Dictionary<Type, TBaseRevision> SplitEntities { get; } = new();

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

    public class ModelBuilderHelper
    {
        private readonly ModelBuilder _builder;
        private readonly string _baseTableName;

        public ModelBuilderHelper(ModelBuilder builder, string baseTableName) =>
            (_builder, _baseTableName) = (builder, baseTableName);

        public void HasKey<TRevision>(Expression<Func<TRevision, object?>> keySelector)
            where TRevision : class, TBaseRevision =>
            _builder.Entity<TRevision>().ToTable(_baseTableName).HasKey(keySelector);

        public void SplittingHasKeyAndName<TRevisionWithSplitting>
            (string tableNameSuffix, Expression<Func<TRevisionWithSplitting, object?>> keySelector)
            where TRevisionWithSplitting : RevisionWithSplitting<TBaseRevision> =>
            _builder.Entity<TRevisionWithSplitting>().Ignore(e => e.NullFieldsBitMask)
                .ToTable($"{_baseTableName}_{tableNameSuffix}").HasKey(keySelector);
    }
}
