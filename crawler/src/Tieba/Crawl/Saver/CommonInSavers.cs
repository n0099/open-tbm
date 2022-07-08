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

        public delegate bool FieldChangeIgnoranceCallback(Type whichPostType, string propertyName, object? originalValue, object? currentValue);
        public record FieldChangeIgnoranceCallbackRecord(FieldChangeIgnoranceCallback Update, FieldChangeIgnoranceCallback Revision);
        protected static readonly FieldChangeIgnoranceCallbackRecord FieldChangeIgnorance = new(
            Update: (whichPostType, propertyName, originalValue, currentValue) =>
            {
                if (whichPostType == typeof(ReplyPost) || whichPostType == typeof(SubReplyPost))
                { // type=3.cdn_src, image url within the content of reply will be changed by each request
                    // type=4.text, the displayed username within user@mentions might change, also will affect replies
                    if (propertyName is nameof(ReplyPost.Content) or nameof(SubReplyPost.Content)) return true;
                }
                if (whichPostType == typeof(ThreadPost))
                {
                    switch (propertyName)
                    {
                        case nameof(ThreadPost.AuthorPhoneType): // will be update by ThreadLateCrawlerAndSaver
                        case nameof(ThreadPost.ZanInfo) when currentValue is null: // possible randomly response with null
                        case nameof(ThreadPost.Location) when currentValue is null: // possible randomly response with null
                        case nameof(ThreadPost.Title) when currentValue is "": // empty string from response will be later set by ReplyCrawlFacade.PostParseCallback()
                        case nameof(ThreadPost.DisagreeNum) when originalValue is not 0 && currentValue is 0: // possible randomly response with 0
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
                        case nameof(ThreadPost.AuthorManagerType) when originalValue is null: // null values will be later set by tieba client 6.0.2 response at ThreadParser.ParsePostsInternal()
                            return true;
                    }
                }
                return false;
            });
    }

    public abstract class CommonInSavers<TSaver> : InternalCommonInSavers where TSaver : CommonInSavers<TSaver>
    {
        protected void SavePostsOrUsers<TPostIdOrUid, TPostOrUser, TRevision, TRevisionNullFields>(
            ILogger<CommonInSavers<TSaver>> logger,
            FieldChangeIgnoranceCallbackRecord tiebaUserFieldChangeIgnorance,
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

                    var whichPostType = typeof(TPostOrUser);
                    var entryIsUser = whichPostType == typeof(TiebaUser);
                    if (FieldChangeIgnorance.Update(whichPostType, pName, p.OriginalValue, p.CurrentValue)
                        || (entryIsUser && tiebaUserFieldChangeIgnorance.Update(whichPostType, pName, p.OriginalValue, p.CurrentValue)))
                    {
                        p.IsModified = false;
                        continue; // skip following revision check
                    }
                    if (FieldChangeIgnorance.Revision(whichPostType, pName, p.OriginalValue, p.CurrentValue)
                        || (entryIsUser && tiebaUserFieldChangeIgnorance.Revision(whichPostType, pName, p.OriginalValue, p.CurrentValue))) continue;

                    var revisionProp = RevisionPropertiesCache[typeof(TRevision)].FirstOrDefault(p2 => p2.Name == pName);
                    if (revisionProp == null)
                    {
                        object? ToHexWhenByteArray(object? value) => value is byte[] bytes ? "0x" + Convert.ToHexString(bytes).ToLowerInvariant() : value;
                        logger.LogWarning("Updating field {} is not existed in revision table, " +
                                          "newValue={}, oldValue={}, newObject={}, oldObject={}",
                            pName, ToHexWhenByteArray(p.CurrentValue), ToHexWhenByteArray(p.OriginalValue),
                            Helper.UnescapedJsonSerialize(currentPostOrUser), Helper.UnescapedJsonSerialize(originalPostOrUser));
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
