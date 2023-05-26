namespace tbm.Crawler.Tieba;

public class EmptyPostListException : TiebaException
{
    public EmptyPostListException(string message) : base(shouldRetry: false, message) { }
}
