namespace tbm.Crawler
{
    public abstract class CommonInParsers
    {
        public static byte[]? SerializedProtoBufOrNullIfEmpty(IMessage? protoBuf)
        {
            if (protoBuf == null) return null;
            var serialized = protoBuf.ToByteArray();
            return serialized.Length == 0 ? null : serialized;
        }

        public static byte[]? SerializedProtoBufWrapperOrNullIfEmpty(Func<IMessage> wrapperFactory) =>
            SerializedProtoBufOrNullIfEmpty(wrapperFactory());
    }
}
