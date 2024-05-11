namespace tbm.Crawler.Tieba.Crawl.Saver;

public abstract class SaverWithRevision<TBaseRevision> : IRevisionProperties
    where TBaseRevision : BaseRevisionWithSplitting
{
    protected delegate void AddRevisionDelegate(CrawlerDbContext db, IEnumerable<TBaseRevision> revision);

    protected virtual IReadOnlyDictionary<Type, AddRevisionDelegate> AddRevisionDelegatesKeyBySplitEntityType =>
        throw new NotSupportedException();

    protected virtual NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName) =>
        throw new NotSupportedException();
}
