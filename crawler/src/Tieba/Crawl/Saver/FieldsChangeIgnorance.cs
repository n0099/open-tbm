using System.Diagnostics.CodeAnalysis;

namespace tbm.Crawler
{
    public record FieldsChangeIgnoranceWrapper(FieldsChangeIgnorance Update, FieldsChangeIgnorance Revision)
    {
        public FieldsChangeIgnoranceWrapper() : this(new(new(), new()))
        {
        }
    }

    public record FieldChangeIgnorance(string Name, bool ShouldTestValue = false, object? TestValue = null);

    public class FieldsChangeIgnorance
    {
        private readonly Dictionary<Type, List<FieldChangeIgnorance>> _dict = new();

        public List<FieldChangeIgnorance> this[Type key]
        {
            init => _dict[key] = value;
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
