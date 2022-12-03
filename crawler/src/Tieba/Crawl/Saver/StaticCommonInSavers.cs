namespace tbm.Crawler
{
    public abstract class StaticCommonInSavers
    { // static field in this non generic class will be shared across all reified generic derived classes
        private static Dictionary<Type, IEnumerable<PropertyInfo>> GetPropsKeyByType(List<Type> types) =>
            types.ToDictionary(t => t, t => t.GetProperties().AsEnumerable());
        protected static readonly Dictionary<Type, IEnumerable<PropertyInfo>> RevisionPropertiesCache = GetPropsKeyByType(new()
            {typeof(ThreadRevision), typeof(ReplyRevision), typeof(SubReplyRevision), typeof(UserRevision)});

        public delegate bool FieldChangeIgnoranceCallback(Type whichPostType, string propName, object? oldValue, object? newValue);
        public record FieldChangeIgnoranceCallbackRecord(FieldChangeIgnoranceCallback Update, FieldChangeIgnoranceCallback Revision);
        protected static readonly FieldChangeIgnoranceCallbackRecord FieldChangeIgnorance = new(
            Update: (whichPostType, propName, oldValue, newValue) =>
            {
                if (whichPostType == typeof(TiebaUser) // possible randomly response with null
                    && propName == nameof(TiebaUser.IpGeolocation) && newValue is null) return true;
                if (whichPostType == typeof(ThreadPost))
                {
                    switch (propName)
                    {
                        // will be update by ThreadLateCrawlerAndSaver
                        case nameof(ThreadPost.AuthorPhoneType):
                        // prevent overwrite existing values of field liker_id which is saved by legacy crawler, and ZanInfo itself is deprecated by tieba so it shouldn't get updated
                        case nameof(ThreadPost.ZanInfo):
                        // possible randomly response with null
                        case nameof(ThreadPost.Geolocation) when newValue is null:
                        // empty string means the author had not write a title
                        // its value generated from the first reply within response of reply crawler will be later set by ReplyCrawlFacade.PostParseHook()
                        case nameof(ThreadPost.Title) when newValue is ""
                            // prevent repeatedly update with different title due to the thread is a multi forum topic thread thus its title can be vary within the forum and within the thread
                                                           || (newValue is not "" && oldValue is not ""):
                        // possible randomly response with 0.NullIfZero()
                        case nameof(ThreadPost.DisagreeNum) when newValue is null && oldValue is not null:
                        // when the latest reply post is deleted and there's no new reply after delete, this field but not LatestReplyTime will be null
                        case nameof(ThreadPost.LatestReplierUid) when newValue is null:
                            return true;
                    }
                }
                // possible randomly response with null
                if (whichPostType == typeof(ReplyPost)
                    && propName == nameof(ReplyPost.SignatureId)
                    && newValue is null && oldValue is not null) return true;
                // possible randomly response with 0 and in the latter responses it will back to normal
                if ((whichPostType == typeof(ReplyPost) || whichPostType == typeof(SubReplyPost))
                    && propName is nameof(ReplyPost.AuthorUid) or nameof(SubReplyPost.AuthorUid)
                    && newValue is 0L && oldValue is not 0L) return true;
                return false;
            },
            Revision: (whichPostType, propName, oldValue, _) =>
            {
                // ignore revision that figures update existing old users that don't have ip geolocation
                if (whichPostType == typeof(TiebaUser)
                    && propName == nameof(TiebaUser.IpGeolocation) && oldValue is null) return true;
                if (whichPostType == typeof(ThreadPost))
                {
                    switch (propName)
                    {
                        // empty string from response has been updated by ReplyCrawlFacade.PostParseHook()
                        case nameof(ThreadPost.Title) when oldValue is "":
                        // null values will be later set by tieba client 6.0.2 response at ThreadParser.ParsePostsInternal()
                        case nameof(ThreadPost.LatestReplierUid) when oldValue is null:
                            return true;
                    }
                }
                return false;
            });
    }
}
