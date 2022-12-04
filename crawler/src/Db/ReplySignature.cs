namespace tbm.Crawler.Db
{
    public class ReplySignature
    {
        public long UserId { get; set; }
        public uint SignatureId { get; set; }
        public byte[] SignatureMd5 { get; set; } = null!;
        public byte[] Signature { get; set; } = null!;
        public uint FirstSeen { get; set; }
        public uint LastSeen { get; set; }
    }
}
