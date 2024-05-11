namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public interface IPostSaver<TPost> where TPost : BasePost
{
    public IFieldChangeIgnorance.FieldChangeIgnoranceDelegates UserFieldChangeIgnorance { get; }
    public PostType CurrentPostType { get; }
    public void TriggerPostSave();
    public SaverChangeSet<TPost> Save(CrawlerDbContext db);
}
