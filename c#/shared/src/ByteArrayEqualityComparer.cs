namespace tbm.Shared
{
    public class ByteArrayEqualityComparer : EqualityComparer<byte[]>
    {
        public override bool Equals(byte[]? x, byte[]? y) =>
            x == y || (x != null && y != null && x.AsSpan().SequenceEqual(y.AsSpan()));

        public override int GetHashCode(byte[] obj)
        {
            var hash = default(HashCode);
            hash.AddBytes(obj);
            return hash.ToHashCode();
        }
    }
}
