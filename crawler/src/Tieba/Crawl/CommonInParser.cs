using System;
using System.Text.Json;
using Google.Protobuf;

namespace tbm.Crawler
{
    public abstract class CommonInParser
    {
        public static byte[]? SerializedProtoBufOrNullIfEmptyValues(IMessage? protoBuf) =>
            JsonSerializer.Serialize(protoBuf) is "\"\"" or "[]" or "null" ? null : protoBuf.ToByteArray();

        public static byte[]? SerializedProtoBufWrapperOrNullIfEmptyValues(Func<IMessage> wrapperFactory) =>
            SerializedProtoBufOrNullIfEmptyValues(wrapperFactory());
    }
}
