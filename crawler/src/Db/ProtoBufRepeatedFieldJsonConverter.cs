using System.Text.Json.Serialization;

namespace tbm.Crawler.Db;

public class ProtoBufRepeatedFieldJsonConverter<T> : JsonConverter<RepeatedField<T>> where T : IMessage
{
    public override RepeatedField<T> Read(ref Utf8JsonReader reader, Type typeToConvert, JsonSerializerOptions options) =>
        throw new NotImplementedException();

    public override void Write(Utf8JsonWriter writer, RepeatedField<T> value, JsonSerializerOptions options) =>
        writer.WriteRawValue(value.ToString());
}
