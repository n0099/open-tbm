namespace tbm.Crawler.Tieba.Crawl.Crawler;

public interface ICrawler<in TResponse, out TPostProtoBuf>
    where TResponse : class, IMessage<TResponse>
    where TPostProtoBuf : class, IMessage<TPostProtoBuf>
{
    public Exception FillExceptionData(Exception e);
    public IReadOnlyCollection<TPostProtoBuf> GetValidPosts(TResponse response, CrawlRequestFlag flag);
    public TbClient.Page? GetResponsePage(TResponse response);
}
