using System.Collections.ObjectModel;
using LinqToDB.Extensions;

namespace tbm.Crawler;

/// <see>https://stackoverflow.com/questions/38316519/replace-parameter-type-in-lambda-expression/38345590#38345590</see>
#pragma warning disable SA1618 // Generic type parameters should be documented
public class ReplaceParameterTypeVisitor<TSource, TTarget> : ExpressionVisitor
#pragma warning restore SA1618 // Generic type parameters should be documented
{
    private ReadOnlyCollection<ParameterExpression>? _parameters;

    protected override Expression VisitParameter(ParameterExpression node) =>
        _parameters?.FirstOrDefault(p => p.Name == node.Name) ??
        (node.Type == typeof(TSource) ? Expression.Parameter(typeof(TTarget), node.Name) : node);

    protected override Expression VisitLambda<T>(Expression<T> node)
    {
        _parameters = VisitAndConvert(node.Parameters, nameof(VisitLambda));
        return Expression.Lambda(Visit(node.Body.Type.IsAnonymous()

            // https://stackoverflow.com/questions/38316519/replace-parameter-type-in-lambda-expression/78560844#78560844
            ? Expression.Convert(node.Body, typeof(object))
            : node.Body), _parameters);
    }

    protected override Expression VisitMember(MemberExpression node) =>
        node.Member.DeclaringType == typeof(TSource)
            ? Expression.Property(Visit(node.Expression)!, node.Member.Name)
            : base.VisitMember(node);
}
