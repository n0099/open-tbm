using Microsoft.EntityFrameworkCore.ChangeTracking;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public abstract class BaseSaver<TBaseRevision>(ILogger<BaseSaver<TBaseRevision>> logger)
#pragma warning disable S1939 // Inheritance list should not be redundant
    : SaverWithRevision<TBaseRevision>, IFieldChangeIgnorance
#pragma warning restore S1939 // Inheritance list should not be redundant
    where TBaseRevision : class, IRevision
{
    protected void SavePostsOrUsers<TPostOrUser, TRevision>(
        CrawlerDbContext db,
        IFieldChangeIgnorance.FieldChangeIgnoranceDelegates userFieldChangeIgnorance,
        Func<TPostOrUser, TRevision> revisionFactory,
        ILookup<bool, TPostOrUser> existingOrNewLookup,
        Func<TPostOrUser, TPostOrUser> existingSelector)
        where TPostOrUser : class
        where TRevision : class, IRevision
    {
        db.Set<TPostOrUser>().AddRange(existingOrNewLookup[false]); // newly added
        var newRevisions = existingOrNewLookup[true].Select(newPostOrUser =>
        {
            var postOrUserInTracking = existingSelector(newPostOrUser);
            var entry = db.Entry(postOrUserInTracking);

            // this will mutate postOrUserInTracking which is referenced by entry
            entry.CurrentValues.SetValues(newPostOrUser);

            bool IsTimestampingFieldName(string name) => name is nameof(IPost.LastSeenAt)
                or nameof(ITimestampedEntity.CreatedAt) or nameof(ITimestampedEntity.UpdatedAt);

            // rollback changes that overwrite original values with the default value 0 or null
            // for all fields of ITimestampedEntity and IPost.LastSeenAt
            // this will also affect the entity instance which postOrUserInTracking references to it
            entry.Properties
                .Where(prop => prop.IsModified && IsTimestampingFieldName(prop.Metadata.Name))
                .ForEach(prop => prop.IsModified = false);

            var revision = default(TRevision);
            var revisionNullFieldsBitMask = 0;
            var whichPostType = typeof(TPostOrUser);
            var entryIsUser = whichPostType == typeof(User);
            foreach (var p in entry.Properties)
            {
                var pName = p.Metadata.Name;
                if (!p.IsModified || IsTimestampingFieldName(pName)) continue;

                if (IFieldChangeIgnorance.GlobalFieldChangeIgnorance.Update(whichPostType, pName, p.OriginalValue, p.CurrentValue)
                    || (entryIsUser && userFieldChangeIgnorance.Update(
                        whichPostType, pName, p.OriginalValue, p.CurrentValue)))
                {
                    p.IsModified = false;
                    continue; // skip following revision check
                }
                if (IFieldChangeIgnorance.GlobalFieldChangeIgnorance.Revision(whichPostType, pName, p.OriginalValue, p.CurrentValue)
                    || (entryIsUser && userFieldChangeIgnorance.Revision(
                        whichPostType, pName, p.OriginalValue, p.CurrentValue)))
                    continue;

                if (IsLatestReplierUser(pName, p, entry)) return null;

                if (!IRevisionProperties.Cache[typeof(TRevision)].TryGetValue(pName, out var revisionProp))
                {
                    object? ToHexWhenByteArray(object? value) =>
                        value is byte[] bytes ? $"0x{Convert.ToHexString(bytes).ToLowerInvariant()}" : value;
                    logger.LogWarning("Updating field {} is not existing in revision table, " +
                                       "newValue={}, oldValue={}, newObject={}, oldObject={}",
                        pName, ToHexWhenByteArray(p.CurrentValue), ToHexWhenByteArray(p.OriginalValue),
                        Helper.UnescapedJsonSerialize(newPostOrUser),
                        Helper.UnescapedJsonSerialize(entry.OriginalValues.ToObject()));
                }
                else
                {
                    revision ??= revisionFactory(postOrUserInTracking);

                    // quote from MSDN https://learn.microsoft.com/en-us/dotnet/api/system.reflection.propertyinfo.setvalue
                    // If the property type of this PropertyInfo object is a value type and value is null
                    // the property will be set to the default value for that type.
                    // https://stackoverflow.com/questions/3049477/propertyinfo-setvalue-and-nulls
                    // this is a desired behavior to convert null values produced by ExtensionMethods.NullIfZero()
                    // back to zeros for some revision fields that had been entity splitting
                    // these split tables will only contain two Superkeys:
                    // the Candidate/Primary Key and the field gets split out
                    // so it's no longer necessary to use NullFieldsBitMasks to identify between
                    // the real null values and unchanged fields that have null as a placeholder
                    revisionProp.SetValue(revision, p.OriginalValue);

                    if (p.OriginalValue != null) continue;

                    // fields that have already split out will not exist in GetRevisionNullFieldBitMask
                    var whichBitToMask = GetRevisionNullFieldBitMask(pName);
                    revisionNullFieldsBitMask |= whichBitToMask; // mask the corresponding field bit with 1
                }
            }
            if (revision != null)
                revision.NullFieldsBitMask = (NullFieldsBitMask?)revisionNullFieldsBitMask.NullIfZero();
            return revision;
        }).OfType<TRevision>().ToList();
        if (newRevisions.Count == 0) return; // quick exit to prevent execute sql with WHERE FALSE clause

        _ = db.Set<TRevision>().UpsertRange(
                newRevisions.Where(rev => !rev.IsAllFieldsIsNullExceptSplit()))
            .NoUpdate().Run();
        newRevisions.OfType<RevisionWithSplitting<TBaseRevision>>()
            .SelectMany(rev => rev.SplitEntities)
            .GroupBy(pair => pair.Key, pair => pair.Value)
            .ForEach(g => RevisionUpsertDelegatesKeyBySplitEntityType[g.Key](db, g));
    }

    private static bool IsLatestReplierUser(string pName, PropertyEntry p, EntityEntry entry)
    {
        // ThreadCrawlFacade.ParseLatestRepliers() will save users with empty string as portrait
        // they will soon be updated by (sub) reply crawler after it find out the latest reply
        // so we should ignore its revision update for all fields
        // ignore entire record is not possible via IFieldChangeIgnorance.GlobalFieldChangeIgnorance.Revision()
        // since it can only determine one field at the time
        if (pName != nameof(User.Portrait) || p.OriginalValue is not "") return false;

        // invokes OriginalValues.ToObject() to get a new instance
        // since postOrUserInTracking is reference to the changed one
        var user = (User)entry.OriginalValues.ToObject();

        // create another user instance with only fields of latest replier filled
        var latestReplier = User.CreateLatestReplier(user.Uid, user.Name, user.DisplayName);

        // if they are same by fields values, the original one is the latest replier
        // that previously generated by ParseLatestRepliers()
        return IsSameUser(user, latestReplier);
    }

    private static bool IsSameUser(User a, User b) =>
        (a.Uid, a.Name, a.DisplayName, a.Portrait, a.PortraitUpdatedAt, a.Gender, a.FansNickname, a.IpGeolocation)
        == (b.Uid, b.Name, b.DisplayName, b.Portrait, b.PortraitUpdatedAt, b.Gender, b.FansNickname, b.IpGeolocation)
        && (a.Icon == b.Icon
            || (a.Icon != null && b.Icon != null && new ByteArrayEqualityComparer().Equals(a.Icon, b.Icon)));
}
