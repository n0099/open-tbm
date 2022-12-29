using System.Text.Encodings.Web;
using System.Text.Unicode;

namespace tbm.Crawler
{
    public abstract class Helper
    {
        public static byte[]? SerializedProtoBufOrNullIfEmpty(IMessage? protoBuf)
        {
            if (protoBuf == null) return null;
            var serialized = protoBuf.ToByteArray();
            return serialized.Length == 0 ? null : serialized;
        }

        public static byte[]? SerializedProtoBufWrapperOrNullIfEmpty(Func<IMessage> wrapperFactory) =>
            SerializedProtoBufOrNullIfEmpty(wrapperFactory());

        private static readonly JsonSerializerOptions UnescapedSerializeOptions =
            new() {Encoder = JavaScriptEncoder.Create(UnicodeRanges.All)};

        public static string UnescapedJsonSerialize<TValue>(TValue value) =>
            JsonSerializer.Serialize(value, UnescapedSerializeOptions);
    }
}
