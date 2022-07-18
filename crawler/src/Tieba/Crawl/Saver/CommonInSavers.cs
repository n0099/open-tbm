namespace tbm.Crawler
{
    public abstract class CommonInSavers<TSaver> : StaticCommonInSavers where TSaver : CommonInSavers<TSaver>
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
