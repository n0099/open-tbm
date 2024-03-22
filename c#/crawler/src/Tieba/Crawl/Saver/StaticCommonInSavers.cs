namespace tbm.Crawler.Tieba.Crawl.Saver;

public abstract class StaticCommonInSavers
{
    public delegate bool FieldChangeIgnoranceDelegate(
        Type whichPostType, string propName, object? oldValue, object? newValue);

    // static field in this non generic class will be shared across all reified generic derived classes
    protected static Dictionary<Type, Dictionary<string, PropertyInfo>> RevisionPropertiesCache { get; } = GetPropsKeyByType(
        [typeof(ThreadRevision), typeof(ReplyRevision), typeof(SubReplyRevision), typeof(UserRevision)]);

    protected static FieldChangeIgnoranceDelegates GlobalFieldChangeIgnorance { get; } = new(
        Update: (whichPostType, propName, oldValue, newValue) =>
        {
            if (whichPostType == typeof(User))
            {
                switch (propName)
                { // possible randomly respond with null
                    case nameof(User.IpGeolocation) when newValue is null:
                    // possible clock drift across multiple response from tieba api
                    // they should sync their servers with NTP
                    /* following sql can track these drift
                    SELECT portraitUpdatedAtDiff, COUNT(*), MAX(uid), MIN(uid), MAX(portraitUpdatedAt), MIN(portraitUpdatedAt)
                    FROM (
                        SELECT uid, portraitUpdatedAt, CAST(portraitUpdatedAt AS SIGNED)
                                - LEAD(CAST(portraitUpdatedAt AS SIGNED)) OVER (PARTITION BY uid ORDER BY time DESC) AS portraitUpdatedAtDiff
                            FROM tbmcr_user WHERE portraitUpdatedAt IS NOT NULL
                    ) AS T
                    WHERE portraitUpdatedAtDiff > -100 AND portraitUpdatedAtDiff < 100
                    GROUP BY portraitUpdatedAtDiff ORDER BY portraitUpdatedAtDiff;
                    */
                    case nameof(User.PortraitUpdatedAt)
                        when Math.Abs((newValue as int? ?? 0) - (oldValue as int? ?? 0)) <= 10:
                        return true;
                }
            }
            if (whichPostType == typeof(ThreadPost))
            {
                switch (propName)
                { // will be update by ThreadLateCrawlerAndSaver
                    case nameof(ThreadPost.AuthorPhoneType):
                    // prevent overwrite existing value of field liker_id which is saved by legacy crawler
                    // and Zan itself is deprecated by tieba so it shouldn't get updated
                    case nameof(ThreadPost.Zan):
                    // possible randomly respond with null
                    case nameof(ThreadPost.Geolocation) when newValue is null:
                    // empty string means the author had not write a title
                    // its value generated from the first reply within response of reply crawler
                    // will be later set by ReplyCrawlFacade.SaveParentThreadTitle()
                    case nameof(ThreadPost.Title)
                        when newValue is ""

                             // prevent repeatedly update with different title
                             // due to the thread is a multi forum topic thread
                             // thus its title can be vary within the forum and within the thread
                             || (newValue is not "" && oldValue is not ""):
                    // possible randomly respond with 0.NullIfZero()
                    case nameof(ThreadPost.DisagreeCount) when newValue is null && oldValue is not null:
                    // when the latest reply post is deleted and there's no new reply after delete
                    // this field but not LatestReplyPostedAt will be null
                    case nameof(ThreadPost.LatestReplierUid) when newValue is null:
                        return true;
                }
            }

            // possible randomly respond with null
            if (whichPostType == typeof(ReplyPost)
                && propName == nameof(ReplyPost.SignatureId)
                && newValue is null && oldValue is not null) return true;

            // possible rarely respond with the protoBuf default value 0
            return propName == nameof(IPost.AuthorUid)
                   && newValue is 0L && oldValue is not null;
        },
        Revision: (whichPostType, propName, oldValue, _) =>
        { // ignore revision that figures update existing old users that don't have ip geolocation
            if (whichPostType == typeof(User)
                && propName == nameof(User.IpGeolocation) && oldValue is null) return true;
            if (whichPostType == typeof(ThreadPost))
            {
                switch (propName)
                { // empty string from response has been updated by ReplyCrawlFacade.PostParseHook()
                    case nameof(ThreadPost.Title) when oldValue is "":
                    // null values will be later set by tieba client 6.0.2 response at ThreadParser.ParsePostsInternal()
                    case nameof(ThreadPost.LatestReplierUid) when oldValue is null:
                        return true;
                }
            }
            return false;
        });

    private static Dictionary<Type, Dictionary<string, PropertyInfo>> GetPropsKeyByType(List<Type> types) =>
        types.ToDictionary(type => type, type => type.GetProperties().ToDictionary(prop => prop.Name));

    public record FieldChangeIgnoranceDelegates(
        FieldChangeIgnoranceDelegate Update,
        FieldChangeIgnoranceDelegate Revision);
}
