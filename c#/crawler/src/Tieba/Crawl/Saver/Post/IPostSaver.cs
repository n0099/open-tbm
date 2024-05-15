namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public interface IPostSaver<TPost> where TPost : BasePost
{
    public PostType CurrentPostType { get; }
    public void OnPostSave();
    public SaverChangeSet<TPost> Save(CrawlerDbContext db);
}
