using System.Collections.ObjectModel;

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
                .HasKey((Expression<Func<TRevision, object?>>)
                    visitor.Visit(keySelector));
            return this;
        }

        public ModelBuilder SplitToTable<TRevisionWithSplitting>(string tableNameSuffix)
            where TRevisionWithSplitting : RevisionWithSplitting<TBaseRevision>
        {
            var visitor = new ReplaceParameterTypeVisitor<TBaseRevision, TRevisionWithSplitting>();
            _ = builder.Entity<TRevisionWithSplitting>()
                .Ignore(e => e.NullFieldsBitMask)
                .ToTable($"{baseTableName}_{tableNameSuffix}")
                .HasKey((Expression<Func<TRevisionWithSplitting, object?>>)
                    visitor.Visit(keySelector));
            return this;
        }

        /// <see>https://stackoverflow.com/questions/38316519/replace-parameter-type-in-lambda-expression/38345590#38345590</see>
        private sealed class ReplaceParameterTypeVisitor<TSource, TTarget> : ExpressionVisitor
        {
            private ReadOnlyCollection<ParameterExpression>? _parameters;

            protected override Expression VisitParameter(ParameterExpression node) =>
                _parameters?.FirstOrDefault(p => p.Name == node.Name) ??
                (node.Type == typeof(TSource) ? Expression.Parameter(typeof(TTarget), node.Name) : node);

            protected override Expression VisitLambda<T>(Expression<T> node)
            {
                _parameters = VisitAndConvert(node.Parameters, nameof(VisitLambda));
                return Expression.Lambda(Visit(node.Body), _parameters);
            }

            protected override Expression VisitMember(MemberExpression node) =>
                node.Member.DeclaringType == typeof(TSource)
                    ? Expression.Property(Visit(node.Expression)!, node.Member.Name)
                    : base.VisitMember(node);
        }
    }
}
