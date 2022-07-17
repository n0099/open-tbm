namespace tbm.Crawler
{
    public abstract class InternalCommonInSavers
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
                        case nameof(ThreadPost.AuthorManagerType) when originalValue is null: // null values will be later set by tieba client 6.0.2 response at ThreadParser.ParsePostsInternal()
                            return true;
                    }
                }
                return false;
            });
    }

    public abstract class CommonInSavers<TSaver> : InternalCommonInSavers where TSaver : CommonInSavers<TSaver>
    {
        protected virtual Dictionary<string, ushort> RevisionNullFieldsBitMasks => null!;

        protected void SavePostsOrUsers<TPostIdOrUid, TPostOrUser, TRevision>(
            ILogger<CommonInSavers<TSaver>> logger,
            FieldChangeIgnoranceCallbackRecord tiebaUserFieldChangeIgnorance,
            IDictionary<TPostIdOrUid, TPostOrUser> postsOrUsers,
            TbmDbContext db,
            Func<TPostOrUser, TRevision> revisionFactory,
            Func<TPostOrUser, bool> isExistPredicate,
            Func<TPostOrUser, TPostOrUser> existedSelector)
            where TPostOrUser : class where TRevision : BaseRevision
        {
            var existedOrNew = postsOrUsers.Values.ToLookup(isExistPredicate);
            db.AddRange(existedOrNew[false]); // newly added
            db.AddRange(existedOrNew[true].Select(currentPostOrUser =>
            {
                var originalPostOrUser = existedSelector(currentPostOrUser);
                var entry = db.Entry(originalPostOrUser);
                entry.CurrentValues.SetValues(currentPostOrUser);
                // prevent override fields of IEntityWithTimestampFields with the default value 0
                entry.Properties.Where(p => p.Metadata.Name is nameof(IEntityWithTimestampFields.CreatedAt)
                    or nameof(IEntityWithTimestampFields.UpdatedAt)).ForEach(p => p.IsModified = false);

                var revision = default(TRevision);
                int? revisionNullFieldsBitMask = null;
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
                            Helper.UnescapedJsonSerialize(currentPostOrUser), Helper.UnescapedJsonSerialize(entry.OriginalValues.ToObject()));
                    }
                    else
                    {
                        revision ??= revisionFactory(originalPostOrUser);
                        revisionProp.SetValue(revision, p.OriginalValue);

                        if (p.OriginalValue != null) continue;
                        revisionNullFieldsBitMask ??= 0;
                        // mask the corresponding field bit with 1
                        revisionNullFieldsBitMask |= RevisionNullFieldsBitMasks[pName];
                    }
                }
                if (revision != null) revision.NullFieldsBitMask = (ushort?)revisionNullFieldsBitMask;
                return revision;
            }).OfType<TRevision>());
        }
    }
}
