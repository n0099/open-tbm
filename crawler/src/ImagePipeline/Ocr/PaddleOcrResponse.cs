using System.Drawing;
using System.Text.Json.Serialization;

namespace tbm.Crawler.ImagePipeline.Ocr;

public record PaddleOcrRequestPayload(IEnumerable<string> Images);

public record PaddleOcrResponse(string Msg, string Status, PaddleOcrResponse.Result[][]? Results)
{
    public static readonly JsonSerializerOptions JsonSerializerOptions = new() { PropertyNamingPolicy = JsonNamingPolicy.CamelCase };

    [JsonConverter(typeof(ResultsConverter))]
    public Result[][]? Results { get; } = Results;

    public record Result(TextBox? TextBox, string? Text, float? Confidence)
    {
        [JsonPropertyName("text_region")]
        public TextBox? TextBox { get; } = TextBox;
    }

    [JsonConverter(typeof(TextBoxJsonConverter))]
    public record TextBox(Coordinate TopLeft, Coordinate TopRight, Coordinate BottomLeft, Coordinate BottomRight)
    {
        public Rectangle ToCircumscribedRectangle() => Rectangle.FromLTRB(
            // https://www.mathopenref.com/coordbounds.html
            Math.Min(TopLeft.X, BottomLeft.X),
            Math.Min(TopLeft.Y, TopRight.Y), // in left-handed cartesian coordinate system the minimum point is the topmost point on Y axis
            Math.Max(TopRight.X, BottomRight.X),
            Math.Max(BottomLeft.Y, BottomRight.Y));

        public float GetRotationDegrees()
        {
            if (TopLeft.X == BottomLeft.X
                && TopRight.X == BottomRight.X
                && TopLeft.Y == TopRight.Y
                && BottomLeft.Y == BottomRight.Y) return 0;
            var xAxisDiff = BottomLeft.X - TopLeft.X;
            var yAxisDiff = BottomLeft.Y - TopLeft.Y;
            // https://stackoverflow.com/questions/13002979/how-to-calculate-rotation-angle-from-rectangle-points
            // https://www.calculator.net/triangle-calculator.html?vc=&vx=4&vy=&va=90&vz=1&vb=&angleunits=d&x=53&y=29
            return (float)Math.Atan2(xAxisDiff, yAxisDiff) * (float)(180 / Math.PI); // radians to degrees
        }
    }

    public record Coordinate(int X, int Y);

    private class ResultsConverter : JsonConverter<Result[][]>
    {
        public override Result[][]? Read(ref Utf8JsonReader reader, Type typeToConvert, JsonSerializerOptions options) =>
            reader.TokenType == JsonTokenType.StartArray ? JsonSerializer.Deserialize<Result[][]>(ref reader, options) : null;

        public override void Write(Utf8JsonWriter writer, Result[][] value, JsonSerializerOptions options) =>
            throw new NotImplementedException();
    }

    private class TextBoxJsonConverter : JsonConverter<TextBox>
    {
        private static Coordinate ReadCoordinate(ref Utf8JsonReader reader)
        {
            if (reader.TokenType != JsonTokenType.StartArray) throw new JsonException();
            var x = 0;
            var y = 0;
            var i = 0;
            while (reader.Read())
            {
                if (reader.TokenType == JsonTokenType.Number)
                {
                    if (i == 0) x = reader.GetInt32();
                    else if (i == 1) y = reader.GetInt32();
                    else throw new JsonException();
                }
                else if (reader.TokenType == JsonTokenType.EndArray) break;
                else throw new JsonException();
                i++;
            }
            return new(x, y);
        }

        public override TextBox Read(ref Utf8JsonReader reader, Type typeToConvert, JsonSerializerOptions options)
        {
            if (reader.TokenType != JsonTokenType.StartArray) throw new JsonException();
            var i = 0;
            var coordinates = new Coordinate[4];
            while (reader.Read())
            {
                if (reader.TokenType == JsonTokenType.StartArray)
                {
                    coordinates[i] = ReadCoordinate(ref reader);
                    if (i > 3) throw new JsonException();
                }
                else if (reader.TokenType == JsonTokenType.EndArray) break;
                else throw new JsonException();
                i++;
            }
            return new(coordinates[0], coordinates[1], coordinates[3], coordinates[2]);
        }

        public override void Write(Utf8JsonWriter writer, TextBox value, JsonSerializerOptions options) =>
            throw new NotImplementedException();
    }
}
