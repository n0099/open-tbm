namespace tbm.Crawler.Tieba.Crawl.Saver;

public interface IRevisionProperties
{
    protected static IDictionary<Type, IDictionary<string, PropertyInfo>> Cache { get; } = GetPropsKeyByType(
        [typeof(ThreadRevision), typeof(ReplyRevision), typeof(SubReplyRevision), typeof(UserRevision)]);

    private static IDictionary<Type, IDictionary<string, PropertyInfo>> GetPropsKeyByType(IEnumerable<Type> types) =>
        types.ToDictionary(type => type, type =>
            (IDictionary<string, PropertyInfo>)type.GetProperties().ToDictionary(prop => prop.Name));
}
