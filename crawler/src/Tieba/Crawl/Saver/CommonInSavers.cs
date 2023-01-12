using System.Linq.Expressions;

namespace tbm.Crawler.Tieba.Crawl.Saver
{
    public abstract class CommonInSavers<TSaver> : StaticCommonInSavers where TSaver : CommonInSavers<TSaver>
    {
        private readonly ILogger<CommonInSavers<TSaver>> _logger;

        protected CommonInSavers(ILogger<CommonInSavers<TSaver>> logger) => _logger = logger;

        protected virtual Dictionary<string, ushort> RevisionNullFieldsBitMasks => throw new NotImplementedException();

        protected void SavePostsOrUsers<TPostOrUser, TRevision>(
            TbmDbContext db,
            FieldChangeIgnoranceCallbackRecord userFieldChangeIgnorance,
            Func<TPostOrUser, TRevision> revisionFactory,
            ILookup<bool, TPostOrUser> existingOrNewLookup,
            Func<TPostOrUser, TPostOrUser> existingSelector,
            Func<TRevision, long> revisionPostOrUserIdSelector,
            Func<IEnumerable<TRevision>, Expression<Func<TRevision, bool>>> existingRevisionPredicate,
            Expression<Func<TRevision, TRevision>> revisionKeySelector)
            where TPostOrUser : class where TRevision : BaseRevision, new()
        {
            db.Set<TPostOrUser>().AddRange(existingOrNewLookup[false]); // newly added
            var newRevisions = existingOrNewLookup[true].Select(newPostOrUser =>
            {
                var postOrUserInTracking = existingSelector(newPostOrUser);
                var entry = db.Entry(postOrUserInTracking);

                entry.CurrentValues.SetValues(newPostOrUser); // this will mutate postOrUserInTracking which is referenced by entry
                // rollback changes on the fields of ITimestampingEntity with the default value 0 or null
                // this will also affects the entity instance which postOrUserInTracking references to it
                entry.Properties.Where(p => p.Metadata.Name
                        is nameof(ITimestampingEntity.CreatedAt) or nameof(ITimestampingEntity.UpdatedAt))
                    .Where(p => p.IsModified).ForEach(p => p.IsModified = false);

                var revision = default(TRevision);
                int? revisionNullFieldsBitMask = null;
                var whichPostType = typeof(TPostOrUser);
                var entryIsUser = whichPostType == typeof(TiebaUser);
                foreach (var p in entry.Properties)
                {
                    var pName = p.Metadata.Name;
                    if (!p.IsModified || pName is nameof(IPost.LastSeenAt)
                            or nameof(ITimestampingEntity.CreatedAt)
                            or nameof(ITimestampingEntity.UpdatedAt)) continue;

                    if (FieldChangeIgnorance.Update(whichPostType, pName, p.OriginalValue, p.CurrentValue)
                        || (entryIsUser && userFieldChangeIgnorance.Update(whichPostType, pName, p.OriginalValue, p.CurrentValue)))
                    {
                        p.IsModified = false;
                        continue; // skip following revision check
                    }
                    if (FieldChangeIgnorance.Revision(whichPostType, pName, p.OriginalValue, p.CurrentValue)
                        || (entryIsUser && userFieldChangeIgnorance.Revision(whichPostType, pName, p.OriginalValue, p.CurrentValue))) continue;

                    // ThreadCrawlFacade.ParseLatestRepliers() will save users with empty string as portrait
                    // they will soon be updated by (sub) reply crawler after it find out the latest reply
                    // so we should ignore its revision update for all fields
                    // ignore entire record is not possible via FieldChangeIgnorance.Revision() since it can only determine one field at the time
                    if (entryIsUser && pName == nameof(TiebaUser.Portrait) && p.OriginalValue is "")
                    { // invokes OriginalValues.ToObject() to get a new instance since postOrUserInTracking is reference to the changed one
                        var user = (TiebaUser)entry.OriginalValues.ToObject();
                        // create another user instance with only fields of latest replier filled
                        var latestReplier = ThreadCrawlFacade.LatestReplierFactory(user.Uid, user.Name, user.DisplayName);
                        // if they are same by fields values, the original one is a latest replier that previously generated by ParseLatestRepliers()
                        if (user.Equals(latestReplier)) return null;
                    }

                    if (!RevisionPropertiesCache[typeof(TRevision)].TryGetValue(pName, out var revisionProp))
                    {
                        object? ToHexWhenByteArray(object? value) => value is byte[] bytes ? "0x" + Convert.ToHexString(bytes).ToLowerInvariant() : value;
                        _logger.LogWarning("Updating field {} is not existing in revision table, " +
                                           "newValue={}, oldValue={}, newObject={}, oldObject={}",
                            pName, ToHexWhenByteArray(p.CurrentValue), ToHexWhenByteArray(p.OriginalValue),
                            Helper.UnescapedJsonSerialize(newPostOrUser), Helper.UnescapedJsonSerialize(entry.OriginalValues.ToObject()));
                    }
                    else
                    {
                        revision ??= revisionFactory(postOrUserInTracking);
                        revisionProp.SetValue(revision, p.OriginalValue);

                        if (p.OriginalValue != null) continue;
                        revisionNullFieldsBitMask ??= 0;
                        // mask the corresponding field bit with 1
                        revisionNullFieldsBitMask |= RevisionNullFieldsBitMasks[pName];
                    }
                }
                if (revision != null) revision.NullFieldsBitMask = (ushort?)revisionNullFieldsBitMask;
                return revision;
            }).OfType<TRevision>().ToList();
            db.TimestampingEntities();

            if (!newRevisions.Any()) return; // quick exit to prevent execute sql with WHERE FALSE clause
            var existingRevisions = db.Set<TRevision>()
                .Where(existing => newRevisions.Select(r => r.TakenAt).Contains(existing.TakenAt))
                .Where(existingRevisionPredicate(newRevisions))
                .Select(revisionKeySelector)
                .ToList();
            db.Set<TRevision>().AddRange(newRevisions
                .ExceptBy(existingRevisions.Select(e => (e.TakenAt, revisionPostOrUserIdSelector(e))),
                    r => (r.TakenAt, revisionPostOrUserIdSelector(r))));
        }
    }
}
