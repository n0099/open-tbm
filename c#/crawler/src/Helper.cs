namespace tbm.Crawler;

#pragma warning disable AV1708 // Type name contains term that should be avoided
public static class Helper
#pragma warning restore AV1708 // Type name contains term that should be avoided
{
    [SuppressMessage("Member Design", "AV1130:Return type in method signature should be an interface to an unchangeable collection")]
    public static byte[]? SerializedProtoBufOrNullIfEmpty(IMessage? protoBuf) =>
        protoBuf == null || protoBuf.CalculateSize() == 0 ? null : protoBuf.ToByteArray();

    [SuppressMessage("Member Design", "AV1130:Return type in method signature should be an interface to an unchangeable collection")]
    public static byte[]? SerializedProtoBufWrapperOrNullIfEmpty<T>(
        IReadOnlyCollection<T>? valuesToWrap,
        Func<IEnumerable<T>, IMessage?> wrapperFactory)
        where T : class, IMessage =>
        valuesToWrap?.Select(message => message.CalculateSize()).Sum() is 0 or null
            ? null
            : SerializedProtoBufOrNullIfEmpty(wrapperFactory(valuesToWrap));

    public static IEnumerable<Content>? ParseThenUnwrapPostContent(byte[]? serializedProtoBuf) =>
        serializedProtoBuf == null ? null : PostContentWrapper.Parser.ParseFrom(serializedProtoBuf).Value;

    public static PostContentWrapper? WrapPostContent(IEnumerable<Content>? contents) =>
        contents == null ? null : new() {Value = {contents}};

    public static void LogDifferentValuesSharingTheSameKeyInEntities<TLoggerCategory, TEntity, TKey, TValue>(
        ILogger<TLoggerCategory> logger,
        IEnumerable<TEntity> entities,
        string keyName,
        Func<TEntity, TKey?> keySelector,
        Func<TEntity, TValue?> valueSelector,
        IEqualityComparer<(TKey?, TValue?)>? keyAndValueComparer = null) => entities
        .GroupBy(keySelector)
        .Where(g => g.Count() > 1)
        .Flatten2()
        .GroupBy(p => (keySelector(p), valueSelector(p)), comparer: keyAndValueComparer)
        .GroupBy(g => g.Key.Item1)
        .Where(gg => gg.Count() > 1)
        .Flatten2()
        .ForEach(g => logger.LogWarning(
            "Multiple entities with different value of field {} sharing the same key \"{}\": {}",
            keyName, g.Key, SharedHelper.UnescapedJsonSerialize(g)));
}
