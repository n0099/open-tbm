namespace tbm.Crawler.Tieba
{
    public class TiebaException : Exception
    {
        public bool ShouldRetry { get; } = true;

        public bool ShouldSilent { get; }

        public TiebaException() { }

        public TiebaException(string message) : base(message) { }

        public TiebaException(bool shouldRetry, string message) : base(message) => ShouldRetry = shouldRetry;

        public TiebaException(string message, Exception inner) : base(message, inner) { }

        public TiebaException(bool shouldRetry, bool shouldSilent)
        {
            ShouldRetry = shouldRetry;
            ShouldSilent = shouldSilent;
        }
    }
}
