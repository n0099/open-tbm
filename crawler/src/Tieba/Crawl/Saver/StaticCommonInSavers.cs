namespace tbm.Crawler
{
    public abstract class StaticCommonInSavers
    { // static field in this non generic class will be shared across all reified generic derived classes
        private static Dictionary<Type, IEnumerable<PropertyInfo>> GetPropsKeyByType(List<Type> types) =>
            types.ToDictionary(t => t, t => t.GetProperties().AsEnumerable());
        protected static readonly Dictionary<Type, IEnumerable<PropertyInfo>> RevisionPropertiesCache = GetPropsKeyByType(new()
            {typeof(ThreadRevision), typeof(ReplyRevision), typeof(SubReplyRevision), typeof(UserRevision)});

        public delegate bool FieldChangeIgnoranceCallback(Type whichPostType, string propertyName, object? originalValue, object? currentValue);
        public record FieldChangeIgnoranceCallbackRecord(FieldChangeIgnoranceCallback Update, FieldChangeIgnoranceCallback Revision);
        protected static readonly FieldChangeIgnoranceCallbackRecord FieldChangeIgnorance = new(
            Update: (whichPostType, propertyName, originalValue, currentValue) =>
            {
                if (whichPostType == typeof(TiebaUser) // possible randomly response with null
                    && propertyName == nameof(TiebaUser.IpGeolocation) && currentValue is null) return true;
                if (whichPostType == typeof(ThreadPost))
                {
                    switch (propertyName)
                    {
                        // will be update by ThreadLateCrawlerAndSaver
                        case nameof(ThreadPost.AuthorPhoneType):
                        // prevent overwrite existing values of field liker_id which is saved by legacy crawler, and ZanInfo itself is deprecated by tieba so it shouldn't get updated
                        case nameof(ThreadPost.ZanInfo):
                        // possible randomly response with null
                        case nameof(ThreadPost.Geolocation) when currentValue is null:
                        // empty string means the author had not write a title
                        // its value generated from the first reply within response of reply crawler will be later set by ReplyCrawlFacade.PostParseHook()
                        case nameof(ThreadPost.Title) when currentValue is ""
                            // prevent repeatedly update with different title due to the thread is a multi forum topic thread thus its title can be vary within the forum and within the thread
                                                           || (currentValue is not "" && originalValue is not ""):
                        // possible randomly response with 0.NullIfZero()
                        case nameof(ThreadPost.DisagreeNum) when currentValue is null && originalValue is not null:
                        // when the latest reply post is deleted and there's no new reply after delete, this field but not LatestReplyTime will be null
                        case nameof(ThreadPost.LatestReplierUid) when currentValue is null:
                            return true;
                    }
                }
                // possible randomly response with null
                if (whichPostType == typeof(ReplyPost)
                    && propertyName == nameof(ReplyPost.SignatureId)
                    && currentValue is null && originalValue is not null) return true;
                // possible randomly response with 0 and in the latter responses it will back to normal
                if ((whichPostType == typeof(ReplyPost) || whichPostType == typeof(SubReplyPost))
                    && propertyName is nameof(ReplyPost.AuthorUid) or nameof(SubReplyPost.AuthorUid)
                    && currentValue is 0L && originalValue is not 0L) return true;
                return false;
            },
            Revision: (whichPostType, propertyName, originalValue, _) =>
            {
                // ignore revision that figures update existing old users that don't have ip geolocation
                if (whichPostType == typeof(TiebaUser)
                    && propertyName == nameof(TiebaUser.IpGeolocation) && originalValue is null) return true;
                if (whichPostType == typeof(ThreadPost))
                {
                    switch (propertyName)
                    {
                        // empty string from response has been updated by ReplyCrawlFacade.PostParseHook()
                        case nameof(ThreadPost.Title) when originalValue is "":
                        // null values will be later set by tieba client 6.0.2 response at ThreadParser.ParsePostsInternal()
                        case nameof(ThreadPost.LatestReplierUid) when originalValue is null:
                            return true;
                    }
                }
                return false;
            });
    }
}
