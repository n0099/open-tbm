namespace tbm.Crawler
{
    public abstract class InternalCommonInSavers
    {
        protected static readonly Dictionary<Type, IEnumerable<PropertyInfo>> RevisionPropertiesCache = new List<Type>
        { // static field in this non generic class will be shared across all reified generic derived classes
            typeof(ThreadRevision), typeof(ReplyRevision), typeof(SubReplyRevision), typeof(UserRevision)
        }.ToDictionary(i => i, i => i.GetProperties().AsEnumerable());
    }

    public abstract class CommonInSavers<TSaver> : InternalCommonInSavers where TSaver : CommonInSavers<TSaver>
    {
        protected void SavePostsOrUsers<TPostIdOrUid, TPostOrUser, TRevision>(
            ILogger<CommonInSavers<TSaver>> logger,
            TbmDbContext db,
            IDictionary<TPostIdOrUid, TPostOrUser> postsOrUsers,
            Func<TPostOrUser, TRevision> revisionFactory,
            Func<TPostOrUser, bool> isExistPredicate,
            Func<TPostOrUser, TPostOrUser> existedSelector) where TRevision : BaseRevision
        {
            var existedOrNew = postsOrUsers.Values.ToLookup(isExistPredicate);
            db.AddRange((IEnumerable<object>)existedOrNew[false]);
            db.AddRange(existedOrNew[true].Select(currentPostOrUser =>
            {
                var originalPostOrUser = existedSelector(currentPostOrUser);
                if (currentPostOrUser == null || originalPostOrUser == null) return default;

                var entry = db.Entry(originalPostOrUser);
                entry.CurrentValues.SetValues(currentPostOrUser);
                // prevent override fields of IEntityWithTimestampFields with the default value 0
                entry.Properties.Where(p => p.Metadata.Name is nameof(IEntityWithTimestampFields.CreatedAt)
                    or nameof(IEntityWithTimestampFields.UpdatedAt)).ForEach(p => p.IsModified = false);

                var revision = default(TRevision);
                foreach (var p in entry.Properties)
                {
                    var pName = p.Metadata.Name;
                    // the value of user gender returned by thread crawler is always 0, so we shouldn't update existing value that is set before
                    if (pName == nameof(TiebaUser.Gender) && p.Metadata.DeclaringEntityType.ClrType == typeof(ThreadPost)) continue;
                    if (!p.IsModified || pName is nameof(IEntityWithTimestampFields.CreatedAt)
                            or nameof(IEntityWithTimestampFields.UpdatedAt)) continue;

                    var revisionProp = RevisionPropertiesCache[typeof(TRevision)].FirstOrDefault(p2 => p2.Name == pName);
                    if (revisionProp == null)
                    {
                        if (pName != nameof(ThreadPost.Title))
                        { // thread title might be set by ReplyCrawlFacade.PostParseCallback()
                            logger.LogWarning("Updating field {} is not existed in revision table, " +
                                              "newValue={}, oldValue={}, newObject={}, oldObject={}",
                                pName, p.CurrentValue, p.OriginalValue,
                                JsonSerializer.Serialize(currentPostOrUser), JsonSerializer.Serialize(originalPostOrUser));
                        }
                    }
                    else
                    {
                        revision ??= revisionFactory(originalPostOrUser);
                        revisionProp.SetValue(revision, p.OriginalValue);
                    }
                }
                return revision;
            }).OfType<TRevision>());
        }
    }
}
