namespace tbm.Crawler.Db.Revision.Splitting;

public abstract class RevisionWithSplitting<TBaseRevision> : BaseRevisionWithSplitting
    where TBaseRevision : BaseRevisionWithSplitting
{
    private readonly Dictionary<Type, TBaseRevision> _splitEntities = [];
    public IReadOnlyDictionary<Type, TBaseRevision> SplitEntities => _splitEntities;

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

    public class ModelBuilder(
        Microsoft.EntityFrameworkCore.ModelBuilder builder,
        string baseTableName,
        Expression<Func<TBaseRevision, object?>> keySelector)
    {
        public ModelBuilder HasBaseTable<TRevision>() where TRevision : TBaseRevision
        {
            var visitor = new ReplaceParameterTypeVisitor<TBaseRevision, TRevision>();
            _ = builder.Entity<TRevision>().ToTable(baseTableName)
                .HasKey((Expression<Func<TRevision, object?>>)visitor.Visit(keySelector));
            return this;
        }

        public ModelBuilder SplitToTable<TRevisionWithSplitting>(string tableNameSuffix)
            where TRevisionWithSplitting : RevisionWithSplitting<TBaseRevision>
        {
            var visitor = new ReplaceParameterTypeVisitor<TBaseRevision, TRevisionWithSplitting>();
            _ = builder.Entity<TRevisionWithSplitting>()
                .Ignore(e => e.NullFieldsBitMask)
                .ToTable($"{baseTableName}_{tableNameSuffix}")
                .HasKey((Expression<Func<TRevisionWithSplitting, object?>>)visitor.Visit(keySelector));
            return this;
        }
    }
}
