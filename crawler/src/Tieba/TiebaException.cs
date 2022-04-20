namespace tbm.Crawler
{
    public class TiebaException : Exception
    {
        public TiebaException() { }

        public TiebaException(string message) : base(message) { }

        public TiebaException(string message, Exception inner) : base(message, inner) { }
    }
}
