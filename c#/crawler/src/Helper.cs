namespace tbm.Crawler;

#pragma warning disable AV1708 // Type name contains term that should be avoided
public static class Helper
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

    public static IEnumerable<Content>? ParseThenUnwrapPostContent(byte[]? serializedProtoBuf) =>
        serializedProtoBuf == null ? null : PostContentWrapper.Parser.ParseFrom(serializedProtoBuf).Value;

    public static PostContentWrapper? WrapPostContent(IEnumerable<Content>? contents) =>
        contents == null ? null : new() {Value = {contents}};
}
