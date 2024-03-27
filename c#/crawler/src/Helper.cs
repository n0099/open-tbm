using System.Text.Encodings.Web;
using System.Text.Unicode;

namespace tbm.Crawler;

#pragma warning disable AV1708 // Type name contains term that should be avoided
public abstract partial class Helper
#pragma warning restore AV1708 // Type name contains term that should be avoided
{
    [SuppressMessage("Member Design", "AV1130:Return type in method signature should be an interface to an unchangeable collection")]
    public static byte[]? SerializedProtoBufOrNullIfEmpty(IMessage? protoBuf) =>
        protoBuf == null || protoBuf.CalculateSize() == 0 ? null : protoBuf.ToByteArray();

    [SuppressMessage("Member Design", "AV1130:Return type in method signature should be an interface to an unchangeable collection")]
    public static byte[]? SerializedProtoBufWrapperOrNullIfEmpty<T>
        (IEnumerable<T>? valuesToWrap, Func<IMessage?> wrapperFactory) where T : class, IMessage =>
        valuesToWrap?.Select(message => message.CalculateSize()).Sum() is 0 or null
            ? null
            : SerializedProtoBufOrNullIfEmpty(wrapperFactory());

    public static IReadOnlyList<Content>? ParseThenUnwrapPostContent(byte[]? serializedProtoBuf) =>
        serializedProtoBuf == null ? null : PostContentWrapper.Parser.ParseFrom(serializedProtoBuf).Value;

    public static PostContentWrapper? WrapPostContent(IReadOnlyList<Content>? contents) =>
        contents == null ? null : new() {Value = {contents}};

    public static void GetNowTimestamp(out Time now) => now = GetNowTimestamp();
    [SuppressMessage("Maintainability", "AV1551:Method overload should call another overload")]
    public static Time GetNowTimestamp() => (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
}
public abstract partial class Helper
{
    private static readonly JsonSerializerOptions UnescapedSerializeOptions =
        new() {Encoder = JavaScriptEncoder.Create(UnicodeRanges.All)};
    public static string UnescapedJsonSerialize<TValue>(TValue value) =>
        JsonSerializer.Serialize(value, UnescapedSerializeOptions);
}
