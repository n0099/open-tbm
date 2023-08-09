namespace tbm.Crawler.Tieba;

public class EmptyPostListException(string message)
    : TiebaException(shouldRetry: false, message);
