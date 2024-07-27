namespace tbm.Crawler.Tieba.Crawl.Saver.Post;

public interface IPostSaver<TPostEntity, TParsedPost>
    where TPostEntity : BasePost
    where TParsedPost : TPostEntity, BasePost.IParsed
{
    public PostType CurrentPostType { get; }
    public bool UserFieldUpdateIgnorance(string propName, object? oldValue, object? newValue);
    public bool UserFieldRevisionIgnorance(string propName, object? oldValue, object? newValue);
    public void OnPostSave();
    public SaverChangeSet<TPostEntity, TParsedPost> Save(CrawlerDbContext db);
}
