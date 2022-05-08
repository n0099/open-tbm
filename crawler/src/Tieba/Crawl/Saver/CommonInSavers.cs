namespace tbm.Crawler
{
    public abstract class InternalCommonInSavers
    { // static field in this non generic class will be shared across all reified generic derived classes
        private static Dictionary<Type, IEnumerable<PropertyInfo>> GetPropDictKeyByTypes(List<Type> types) =>
            types.ToDictionary(i => i, i => i.GetProperties().AsEnumerable());
        protected static readonly Dictionary<Type, IEnumerable<PropertyInfo>> RevisionPropertiesCache = GetPropDictKeyByTypes(new()
            {typeof(ThreadRevision), typeof(ReplyRevision), typeof(SubReplyRevision), typeof(UserRevision)});
        protected static readonly Dictionary<Type, IEnumerable<PropertyInfo>> RevisionNullFieldsPropertiesCache = GetPropDictKeyByTypes(new()
            {typeof(ThreadRevisionNullFields), typeof(ReplyRevisionNullFields), typeof(SubReplyRevisionNullFields), typeof(UserRevisionNullFields)});

        protected static readonly FieldsChangeIgnoranceWrapper FieldsChangeIgnorance = new(
            Update: new()
            {
                [typeof(ReplyPost)] = new() {new(nameof(ReplyPost.Content))}, // type=3.cdn_src, image url within the content of reply will be changed by each request
                [typeof(SubReplyPost)] = new() {new(nameof(SubReplyPost.Content))}, // type=4.text, the displayed username within user@mentions might change, also will affect replies
                [typeof(ThreadPost)] = new()
                {
                    new(nameof(ThreadPost.AuthorPhoneType)), // will be update by ThreadLateCrawlerAndSaver
                    new(nameof(ThreadPost.ZanInfo), true), // possible null values from response
                    new(nameof(ThreadPost.Location), true), // possible null values from response
                    new(nameof(ThreadPost.Title), true, "") // empty string from response will be later set by ReplyCrawlFacade.PostParseCallback()
                }
            },
            Revision: new()
            {
                [typeof(ThreadPost)] = new() {new(nameof(ThreadPost.Title))} // empty string from response will be later set by ReplyCrawlFacade.PostParseCallback()
            }
        );
    }

    public abstract class CommonInSavers<TSaver> : InternalCommonInSavers where TSaver : CommonInSavers<TSaver>
    {
        protected void SavePostsOrUsers<TPostIdOrUid, TPostOrUser, TRevision, TRevisionNullFields>(
            ILogger<CommonInSavers<TSaver>> logger,
            FieldsChangeIgnoranceWrapper additionalFieldsChangeIgnorance,
            IDictionary<TPostIdOrUid, TPostOrUser> postsOrUsers,
            TbmDbContext db,
            Func<TPostOrUser, TRevision> revisionFactory,
            Func<TRevisionNullFields> revisionNullFieldsFactory,
            Func<TPostOrUser, bool> isExistPredicate,
            Func<TPostOrUser, TPostOrUser> existedSelector)
            where TRevision : BaseRevision where TRevisionNullFields: IMessage<TRevisionNullFields>
        {
            var existedOrNew = postsOrUsers.Values.ToLookup(isExistPredicate);
            db.AddRange(existedOrNew[false].OfType<object>());
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
                var revisionNullFields = default(TRevisionNullFields);
                foreach (var p in entry.Properties)
                {
                    var pName = p.Metadata.Name;
                    if (!p.IsModified || pName is nameof(IEntityWithTimestampFields.CreatedAt)
                            or nameof(IEntityWithTimestampFields.UpdatedAt)) continue;

                    if (FieldsChangeIgnorance.Update.TestShouldIgnore<TPostOrUser>(additionalFieldsChangeIgnorance.Update, pName, p.CurrentValue))
                    {
                        p.IsModified = false;
                        continue; // skip following revision check
                    }
                    if (FieldsChangeIgnorance.Revision.TestShouldIgnore<TPostOrUser>(additionalFieldsChangeIgnorance.Revision, pName, p.OriginalValue)) continue;

                    var revisionProp = RevisionPropertiesCache[typeof(TRevision)].FirstOrDefault(p2 => p2.Name == pName);
                    if (revisionProp == null)
                    {
                        logger.LogWarning("Updating field {} is not existed in revision table, " +
                                          "newValue={}, oldValue={}, newObject={}, oldObject={}",
                            pName, p.CurrentValue is byte[] bytes ? Convert.ToHexString(bytes) : p.CurrentValue,
                            p.OriginalValue is byte[] bytes2 ? Convert.ToHexString(bytes2) : p.OriginalValue,
                            JsonSerializer.Serialize(currentPostOrUser), JsonSerializer.Serialize(originalPostOrUser));
                    }
                    else
                    {
                        revision ??= revisionFactory(originalPostOrUser);
                        revisionProp.SetValue(revision, p.OriginalValue);

                        if (p.OriginalValue != null) continue;
                        revisionNullFields ??= revisionNullFieldsFactory();
                        RevisionNullFieldsPropertiesCache[typeof(TRevisionNullFields)].First(p2 => p2.Name == pName).SetValue(revisionNullFields, true);
                    }
                }
                if (revision != null && revisionNullFields != null) revision.NullFields = revisionNullFields.ToByteArray();
                return revision;
            }).OfType<TRevision>());
        }
    }
}
