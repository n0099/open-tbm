namespace tbm.Crawler.Db;

public class ProtoBufRepeatedFieldJsonConverter<TProtoBuf>
    : JsonConverter<RepeatedField<TProtoBuf>> where TProtoBuf : class, IMessage
{
    public override RepeatedField<TProtoBuf> Read
        (ref Utf8JsonReader reader, Type typeToConvert, JsonSerializerOptions options) =>
        throw new NotImplementedException();

    public override void Write
        (Utf8JsonWriter writer, RepeatedField<TProtoBuf> value, JsonSerializerOptions options) =>
        writer.WriteRawValue(value.ToString());
}
