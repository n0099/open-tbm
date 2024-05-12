using System.Diagnostics.CodeAnalysis;
using System.Text.Encodings.Web;
using System.Text.Json;
using System.Text.Unicode;

namespace tbm.Shared;

#pragma warning disable AV1708 // Type name contains term that should be avoided
public static class SharedHelper
#pragma warning restore AV1708 // Type name contains term that should be avoided
{
    public static void GetNowTimestamp(out UInt32 now) => now = GetNowTimestamp();
    [SuppressMessage("Maintainability", "AV1551:Method overload should call another overload")]
    public static UInt32 GetNowTimestamp() => (UInt32)DateTimeOffset.Now.ToUnixTimeSeconds();

    private static readonly JsonSerializerOptions UnescapedSerializeOptions =
        new() {Encoder = JavaScriptEncoder.Create(UnicodeRanges.All)};
    public static string UnescapedJsonSerialize<TValue>(TValue value) =>
        JsonSerializer.Serialize(value, UnescapedSerializeOptions);
}
