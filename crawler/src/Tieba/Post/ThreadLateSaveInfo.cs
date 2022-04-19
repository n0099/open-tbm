namespace tbm.Crawler
{
    public class ThreadLateSaveInfo
    {
        public string? AuthorPhoneType { get; set; }
        [BlobTypeProtoBuf] public string? AntiSpamInfo { get; set; }
    }
}
