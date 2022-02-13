using System;
using System.Collections.Generic;
using System.Linq;
using System.Reflection;
using System.Text.Json;
using Json.More;
using Microsoft.Extensions.Logging;

namespace tbm.Crawler
{
    public abstract class CommonInPostAndUser
    {
        protected abstract ILogger<object> Logger { get; init; }

        protected static string? RawJsonOrNullWhenEmpty(JsonElement json)
        {
            var raw = json.GetRawText();
            return raw is @"""""" or "[]" ? null : raw;
        }

        protected void SavePostsOrUsers<TPostIdOrUid, TPostOrUser, TRevision>(TbmDbContext db,
            IDictionary<TPostIdOrUid, TPostOrUser> postsOrUsers,
            Func<TPostOrUser, bool> isExistPredicate,
            Func<TPostOrUser, TPostOrUser> existedSelector,
            Func<uint, TPostOrUser, TRevision> revisionFactory)
        {
            var existedOrNew = postsOrUsers.Values.ToLookup(isExistPredicate);
            db.AddRange((IEnumerable<object>)GetRevisionsForObjectsThenMerge(existedOrNew[true], existedSelector, revisionFactory));
            var newPostsOrUsers = ((IEnumerable<object>)existedOrNew[false]).ToList();
            if (newPostsOrUsers.Any()) db.AddRange(newPostsOrUsers);
        }

        private IEnumerable<TRevision> GetRevisionsForObjectsThenMerge<TObject, TRevision>(
            IEnumerable<TObject> newObjects,
            Func<TObject, TObject> oldObjectSelector,
            Func<uint, TObject, TRevision> revisionFactory)
        {
            var objectProps = typeof(TObject).GetProperties()
                .Where(p => p.Name is not (nameof(IEntityWithTimestampFields.CreatedAt) or nameof(IEntityWithTimestampFields.UpdatedAt))).ToList();
            var revisionProps = typeof(TRevision).GetProperties();
            var nowTimestamp = (uint)DateTimeOffset.Now.ToUnixTimeSeconds();

            return newObjects.Select(newObj =>
            {
                var revision = default(TRevision);
                var oldObj = oldObjectSelector(newObj);
                foreach (var p in objectProps)
                {
                    var newValue = p.GetValue(newObj);
                    var oldValue = p.GetValue(oldObj);
                    var isJsonEqual = false;
                    if (oldValue is string o && newValue is string n && p.GetCustomAttribute<JsonType>() != null)
                    { // serialize the value of json type fields which read from db
                      // for further compare with newValue which have been re-serialized in RawJsonOrNullWhenEmpty()
                        using var oldJson = JsonDocument.Parse(o);
                        using var newJson = JsonDocument.Parse(n);
                        isJsonEqual = oldJson.IsEquivalentTo(newJson);
                    }

                    if (isJsonEqual || Equals(oldValue, newValue)) continue;
                    // ef core will track changes on oldObj via reflection
                    p.SetValue(oldObj, newValue);

                    var revisionProp = revisionProps.FirstOrDefault(p2 => p2.Name == p.Name);
                    if (revisionProp == null)
                        Logger.LogWarning("updating field {} is not existed in revision table, " +
                                          "newValue={}, oldValue={}, newObject={}, oldObject={}",
                            p.Name, newValue, oldValue, JsonSerializer.Serialize(newObj), JsonSerializer.Serialize(oldObj));
                    else
                    {
                        revision ??= revisionFactory(nowTimestamp, newObj);
                        revisionProp.SetValue(revision, newValue);
                    }
                }

                return revision;
            }).OfType<TRevision>();
        }
    }
}
