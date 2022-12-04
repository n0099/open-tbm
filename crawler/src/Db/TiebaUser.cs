namespace tbm.Crawler
{
    public class TiebaUser : IEntityWithTimestampFields, IEquatable<TiebaUser>
    {
        [Key] public long Uid { get; set; }
        public string? Name { get; set; }
        public string? DisplayName { get; set; }
        public string Portrait { get; set; } = "";
        public uint? PortraitUpdateTime { get; set; }
        public ushort? Gender { get; set; }
        public string? FansNickname { get; set; }
        public byte[]? Icon { get; set; }
        public string? IpGeolocation { get; set; }
        public uint CreatedAt { get; set; }
        public uint? UpdatedAt { get; set; }

        public bool Equals(TiebaUser? other)
        {
            if (other is null) return false;
            if (ReferenceEquals(this, other)) return true;
            return Uid == other.Uid
                   && Name == other.Name
                   && DisplayName == other.DisplayName
                   && Portrait == other.Portrait
                   && PortraitUpdateTime == other.PortraitUpdateTime
                   && Gender == other.Gender
                   && FansNickname == other.FansNickname
                   && (Icon == other.Icon
                       || (Icon != null && other.Icon != null && Icon.SequenceEqual(other.Icon)))
                   && IpGeolocation == other.IpGeolocation;
        }

        public override bool Equals(object? obj)
        {
            if (obj is null) return false;
            if (ReferenceEquals(this, obj)) return true;
            return obj.GetType() == GetType() && Equals((TiebaUser)obj);
        }

        public override int GetHashCode()
        {
            var hashCode = new HashCode();
            // ReSharper disable NonReadonlyMemberInGetHashCode
            hashCode.Add(Uid);
            hashCode.Add(Name);
            hashCode.Add(DisplayName);
            hashCode.Add(Portrait);
            hashCode.Add(PortraitUpdateTime);
            hashCode.Add(Gender);
            hashCode.Add(FansNickname);
            hashCode.AddBytes(Icon);
            hashCode.Add(IpGeolocation);
            // ReSharper restore NonReadonlyMemberInGetHashCode
            return hashCode.ToHashCode();
        }
    }
}
