using System.Globalization;

namespace tbm.Shared;

public class NpgsqlCamelCaseNameTranslator : INpgsqlNameTranslator
{
    public string TranslateTypeName(string clrName) => TranslateMemberName(clrName);

    /// <see>https://github.com/efcore/EFCore.NamingConventions/blob/0d19b13a8e62ec20779c3cca03c27f200b5b7458/EFCore.NamingConventions/Internal/CamelCaseNameRewriter.cs#L13</see>
    /// <see>https://github.com/npgsql/npgsql/pull/1690</see>
    public string TranslateMemberName(string clrName) =>
        char.ToLower(clrName[0], CultureInfo.InvariantCulture) + clrName[1..];
}
