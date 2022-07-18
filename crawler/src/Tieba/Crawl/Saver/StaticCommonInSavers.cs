namespace tbm.Crawler
{
    public abstract class StaticCommonInSavers
    { // static field in this non generic class will be shared across all reified generic derived classes
        private static Dictionary<Type, IEnumerable<PropertyInfo>> GetPropDictKeyByTypes(List<Type> types) =>
            types.ToDictionary(i => i, i => i.GetProperties().AsEnumerable());
        protected static readonly Dictionary<Type, IEnumerable<PropertyInfo>> RevisionPropertiesCache = GetPropDictKeyByTypes(new()
            {typeof(ThreadRevision), typeof(ReplyRevision), typeof(SubReplyRevision), typeof(UserRevision)});

        public delegate bool FieldChangeIgnoranceCallback(Type whichPostType, string propertyName, object? originalValue, object? currentValue);
        public record FieldChangeIgnoranceCallbackRecord(FieldChangeIgnoranceCallback Update, FieldChangeIgnoranceCallback Revision);
        protected static readonly FieldChangeIgnoranceCallbackRecord FieldChangeIgnorance = new(
            Update: (whichPostType, propertyName, originalValue, currentValue) =>
            {
                if (whichPostType == typeof(TiebaUser) && propertyName == nameof(TiebaUser.IpGeolocation) && currentValue is null) return true; // possible randomly response with null
                if (whichPostType == typeof(ThreadPost))
                {
                    switch (propertyName)
                    {
                        case nameof(ThreadPost.AuthorPhoneType): // will be update by ThreadLateCrawlerAndSaver
                        case nameof(ThreadPost.ZanInfo): // prevent overwrite existing values of field liker_id which is saved by legacy crawler, and ZanInfo itself is deprecated by tieba so it shouldn't get updated
                        case nameof(ThreadPost.Geolocation) when currentValue is null: // possible randomly response with null
                        case nameof(ThreadPost.Title) when currentValue is "": // empty string means the author had not write a title, its value generated from the first reply within response of reply crawler will be later set by ReplyCrawlFacade.PostParseCallback()
                        case nameof(ThreadPost.DisagreeNum) when currentValue is null && originalValue is not null: // possible randomly response with 0.NullIfZero()
                        case nameof(ThreadPost.LatestReplierUid) when currentValue is null: // when the latest reply post is deleted and there's no new reply after delete, this field but not LatestReplyTime will be null
                            return true;
                    }
                }
                if (whichPostType == typeof(ReplyPost))
                {
                    switch (propertyName)
                    {
                        case nameof(ReplyPost.AuthorUid) when currentValue is 0L && originalValue is not 0L: // possible randomly response with 0 and in the latter responses it will back to normal
                        case nameof(ReplyPost.SignatureId) when currentValue is null && originalValue is not null: // possible randomly response with null
                            return true;
                    }
                }
                return false;
            },
            Revision: (whichPostType, propertyName, originalValue, _) =>
            {
                if (whichPostType == typeof(ThreadPost))
                {
                    switch (propertyName)
                    {
                        case nameof(ThreadPost.Title) when originalValue is "": // empty string from response has been updated by ReplyCrawlFacade.PostParseCallback()
                        case nameof(ThreadPost.LatestReplierUid) when originalValue is null: // null values will be later set by tieba client 6.0.2 response at ThreadParser.ParsePostsInternal()
                            return true;
                    }
                }
                return false;
            });
    }
}
