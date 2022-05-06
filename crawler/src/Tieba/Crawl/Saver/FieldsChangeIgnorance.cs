using System.Diagnostics.CodeAnalysis;

namespace tbm.Crawler
{
    public record FieldsChangeIgnoranceWrapper(FieldsChangeIgnorance Update, FieldsChangeIgnorance Revision)
    {
        public FieldsChangeIgnoranceWrapper() : this(new(new(), new())) { }
    }

    public record FieldChangeIgnorance(string Name, bool ShouldTestValue = false, object? TestValue = null);

    public class FieldsChangeIgnorance
    {
        private readonly Dictionary<Type, List<FieldChangeIgnorance>> _dict = new();

        public List<FieldChangeIgnorance> this[Type key]
        {
            init
            {
#if DEBUG
                Func<PropertyInfo, bool> IsSameNamePredicate(FieldChangeIgnorance i) => p => p.Name == i.Name;
                var props = key.GetProperties();
                value.Where(i =>
                {
                    var exists = props.Any(IsSameNamePredicate(i));
                    return exists ? exists : throw new($"Property {i.Name} doesn't exists on type {key.Name}");
                }).Where(i => i.ShouldTestValue).ForEach(i =>
                {
                    var propType = props.First(IsSameNamePredicate(i)).PropertyType.UnderlyingSystemType;
                    Type? propNullableType = null;
                    // https://stackoverflow.com/questions/8550209/c-sharp-reflection-how-to-get-the-type-of-a-nullableint/8550614#8550614
                    if (propType.IsGenericType && propType.GetGenericTypeDefinition() == typeof(Nullable<>))
                        propNullableType = Nullable.GetUnderlyingType(propType);

                    var valueType = i.TestValue?.GetType().UnderlyingSystemType;
                    if (valueType == propType || valueType == propNullableType || (propNullableType != null && valueType == null)) return;
                    throw new($"The type of given test value doesn't match with the type of property {key.Name}.{i.Name}");
                });
                if (_dict.ContainsKey(key)) throw new($"Duplicate key for {key.Name}");
#endif
                _dict[key] = value;
            }
        }

        public bool TestShouldIgnore<T>(FieldsChangeIgnorance addition, string name, object? value)
        {
            _ = TryGetValue(typeof(T), out var ignoranceList);
            _ = addition.TryGetValue(typeof(T), out var ignoranceList2);
            return (ignoranceList ?? Enumerable.Empty<FieldChangeIgnorance>())
                .Concat(ignoranceList2 ?? Enumerable.Empty<FieldChangeIgnorance>()).Where(i => i.Name == name)
                .Select(ignorance => !ignorance.ShouldTestValue || Equals(ignorance.TestValue, value)).FirstOrDefault();
        }

        private bool TryGetValue(Type key, [MaybeNullWhen(false)] out List<FieldChangeIgnorance> value) => _dict.TryGetValue(key, out value);
    }
}
