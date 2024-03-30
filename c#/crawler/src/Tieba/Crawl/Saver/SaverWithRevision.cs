namespace tbm.Crawler.Tieba.Crawl.Saver;

public abstract class SaverWithRevision<TBaseRevision> : IRevisionProperties
    where TBaseRevision : class, IRevision
{
    protected delegate void RevisionUpsertDelegate(CrawlerDbContext db, IEnumerable<TBaseRevision> revision);

    protected virtual IDictionary<Type, RevisionUpsertDelegate> RevisionUpsertDelegatesKeyBySplitEntityType =>
        throw new NotSupportedException();

    protected virtual NullFieldsBitMask GetRevisionNullFieldBitMask(string fieldName) =>
        throw new NotSupportedException();
}
