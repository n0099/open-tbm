namespace tbm.Crawler;

public static class ExtensionMethods
{
    /// <summary>
    ///     Returns a random long from min (inclusive) to max (exclusive)
    /// </summary>
    /// <param name="random">The given random instance</param>
    /// <param name="min">The inclusive minimum bound</param>
    /// <param name="max">The exclusive maximum bound.  Must be greater than min</param>
    /// <see>https://stackoverflow.com/questions/6651554/random-number-in-long-range-is-this-the-way/13095144#13095144</see>
    public static long NextLong(this Random random, long min, long max)
    {
        Guard.IsLessThanOrEqualTo(min, max);

        var uRange = (ulong)(max - min);
        ulong ulongRand;
        do
        {
            var buf = new byte[8];
            random.NextBytes(buf);
            ulongRand = unchecked((ulong)BitConverter.ToInt64(buf, 0));
        } while (ulongRand > ulong.MaxValue - (((ulong.MaxValue % uRange) + 1) % uRange));

        return (long)(ulongRand % uRange) + min;
    }

    public static string GetStrProp(this JsonElement el, string propName) =>
        el.GetProperty(propName).GetString() ?? "";

    public static Exception ExtractInnerExceptionsData(this Exception e)
    {
        var inner = e.InnerException;
        do
        { // recursive merge all data of exceptions into e.Data
            if (inner == null) continue;
            foreach (var dataKey in inner.Data.Keys)
                e.Data[dataKey] = inner.Data[dataKey];
            inner = inner.InnerException;
        } while (inner != null);

        return e;
    }

    /// <see>https://stackoverflow.com/questions/9314172/getting-all-messages-from-innerexceptions/9314368#9314368</see>
    public static IEnumerable<Exception> GetInnerExceptions(this Exception ex)
    {
        Guard.IsNotNull(ex);

        var inner = ex;
        do
        {
            yield return inner;
            inner = inner.InnerException;
        } while (inner != null);
    }

    public static void SetIfNotNull<T1, T2>(this IDictionary<T1, T2> dict, T1 key, T2? value) where T2 : class
    {
        if (value != null) dict[key] = value;
    }

    /// <see>https://github.com/npgsql/npgsql/issues/4437</see>
    /// <see>https://github.com/dotnet/efcore/issues/32092#issuecomment-2221633692</see>
    [SuppressMessage("StyleCop.CSharp.DocumentationRules", "SA1618:Generic type parameters should be documented")]
    public static IQueryable<TEntity> WhereOrContainsValues<TEntity, TToCompare>(
        this IQueryable<TEntity> queryable,
        IEnumerable<TToCompare> valuesToCompare,
        IEnumerable<Func<TToCompare, Expression<Func<TEntity, bool>>>> comparatorExpressionFactories) =>
        queryable.Where(valuesToCompare.Aggregate(
            LinqKit.PredicateBuilder.New<TEntity>(),
            (outerPredicate, valueToCompare) => outerPredicate.Or(
                comparatorExpressionFactories.Aggregate(
                    LinqKit.PredicateBuilder.New<TEntity>(),
                    (innerPredicate, expressionFactory) =>
                        innerPredicate.And(expressionFactory(valueToCompare))))));

    /// <see>https://stackoverflow.com/questions/67666649/lambda-linq-with-contains-criteria-for-multiple-keywords/67666993#67666993</see>
    [SuppressMessage("StyleCop.CSharp.DocumentationRules", "SA1618:Generic type parameters should be documented")]
    public static IQueryable<T> FilterByItems<T, TItem>(
        this IQueryable<T> query,
        IEnumerable<TItem> items,
        Expression<Func<T, TItem, bool>> filterPattern,
        bool isOr = true)
    {
        var predicate = items.Aggregate<TItem, Expression?>(null, (current, item) =>
        {
            var itemExpr = Expression.Constant(item);
            var itemCondition = FilterByItemsExpressionReplacer
                .Replace(filterPattern.Body, filterPattern.Parameters[1], itemExpr);

            return current == null
                ? itemCondition
#pragma warning disable S3358 // Ternary operators should not be nested
                : Expression.MakeBinary(isOr ? ExpressionType.OrElse : ExpressionType.AndAlso,
#pragma warning restore S3358 // Ternary operators should not be nested
                    current, itemCondition);
        }) ?? Expression.Constant(false);
        var filterLambda = Expression.Lambda<Func<T, bool>>(predicate, filterPattern.Parameters[0]);

        return query.Where(filterLambda);
    }

    private sealed class FilterByItemsExpressionReplacer(IDictionary<Expression, Expression> replaceMap) : ExpressionVisitor
    {
        private readonly IDictionary<Expression, Expression> _replaceMap =
            replaceMap ?? throw new ArgumentNullException(nameof(replaceMap));

        public static Expression Replace(Expression expr, Expression toReplace, Expression toExpr) =>
            new FilterByItemsExpressionReplacer(new Dictionary<Expression, Expression> {{toReplace, toExpr}})
                .Visit(expr);

        [return: NotNullIfNotNull(nameof(node))]
        public override Expression? Visit(Expression? node) =>
            node != null && _replaceMap.TryGetValue(node, out var replacement)
                ? replacement
                : base.Visit(node);
    }
}
