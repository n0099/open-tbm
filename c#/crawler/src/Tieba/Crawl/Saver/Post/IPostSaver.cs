namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public interface IPostSaver<TPost> where TPost : BasePost
{
    public PostType CurrentPostType { get; }
    public bool UserFieldUpdateIgnorance(string propName, object? oldValue, object? newValue);
    public bool UserFieldRevisionIgnorance(string propName, object? oldValue, object? newValue);
    public void OnPostSave();
    public SaverChangeSet<TPost> Save(CrawlerDbContext db);
}
