namespace tbm.Crawler.Tieba;

[SuppressMessage("Roslynator", "RCS1194:Implement exception constructors.")]
public class EmptyPostListException(string message)
    : TiebaException(shouldRetry: false, message);
