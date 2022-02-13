using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using Autofac;
using Microsoft.Extensions.Logging;
using static System.Text.Json.JsonElement;
using Fid = System.UInt32;
using Page = System.UInt32;

namespace tbm.Crawler
{
    public abstract class BaseCrawler<TPost> where TPost : IPost
    {
        protected readonly ILogger<BaseCrawler<TPost>> Logger;
        private readonly ClientRequesterTcs _requesterTcs;
        private readonly ClientRequester _requester;
        private readonly CrawlerLocks _locks; // singleton for every derived class
        private readonly ulong _lockIndex;
        protected readonly Fid Fid;
        protected readonly ConcurrentDictionary<ulong, TPost> Posts = new();
        protected readonly UserParser Users;

        protected abstract Exception FillExceptionData(Exception e);
        protected abstract Task<JsonElement> CrawlSinglePage(Page page);
        protected abstract ArrayEnumerator GetValidPosts(JsonElement json);
        protected abstract void ParsePosts(ArrayEnumerator posts);
        protected abstract void SavePosts(TbmDbContext db);

        protected BaseCrawler(ILogger<BaseCrawler<TPost>> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            UserParser userParser,
            (CrawlerLocks, ulong) lockAndIndex,
            uint fid)
        {
            Logger = logger;
            _requester = requester;
            _requesterTcs = requesterTcs;
            Users = userParser;
            (_locks, _lockIndex) = lockAndIndex;
            Fid = fid;
        }

        public static string? RawJsonOrNullWhenEmpty(JsonElement json) =>
            // serialize to json for further compare with the field value of saved post read from db in SavePosts()
            json.GetRawText() is @"""""" or "[]" ? null : JsonSerializer.Serialize(json);

        public void SavePosts()
        {
            using var scope = Program.Autofac.BeginLifetimeScope();
            var db = scope.Resolve<TbmDbContext.New>()(Fid);
            using var transaction = db.Database.BeginTransaction();
            SavePosts(db);
            Users.SaveUsers(db);
            db.SaveChanges();
            transaction.Commit();
        }

        public async Task<BaseCrawler<TPost>> CrawlRange(Page startPage, Page endPage = Page.MaxValue)
        { // cancel when startPage is already locked
            if (!_locks.AcquireRange(_lockIndex, new[] {startPage}).Any()) return this;
            if (!await CatchCrawlException(async () =>
                {
                    var startPageJson = await CrawlSinglePage(startPage);
                    ValidateJsonThenParse(startPageJson);
                    endPage = Math.Min(Page.Parse(startPageJson.GetProperty("page").GetProperty("total_page").GetString() ?? ""), endPage);
                }, startPage))
                await CrawlRange(Enumerable.Range((int)(startPage + 1), (int)(endPage - startPage)).Select(i => (Page)i));

            return this;
        }

        private async Task CrawlRange(IEnumerable<Page> pages) =>
            await Task.WhenAll(_locks.AcquireRange(_lockIndex, pages).Shuffle().Select(async page =>
            {
                await CatchCrawlException(async () => ValidateJsonThenParse(await CrawlSinglePage(page)), page);
            }));

        private async Task<bool> CatchCrawlException(Func<Task> callback, Page page)
        {
            try
            {
                await callback();
                return false;
            }
            catch (Exception e)
            {
                e.Data["page"] = page;
                e.Data["fid"] = Fid;
                Logger.Log(e is TiebaException ? LogLevel.Warning : LogLevel.Error, FillExceptionData(e), "exception");
                _requesterTcs.Decrease();
                _locks.AcquireFailed(_lockIndex, page);
                return true;
            }
            finally
            {
                _locks.ReleaseLock(_lockIndex, page);
            }
        }

        protected async Task<JsonElement> RequestJson(string url, Dictionary<string, string> param)
        {
            await using var stream = (await _requester.Post(url, param)).EnsureSuccessStatusCode().Content.ReadAsStream();
            using var doc = JsonDocument.Parse(stream);
            return doc.RootElement.Clone();
        }

        protected virtual void ValidateJsonThenParse(JsonElement json) => ParsePosts(GetValidPosts(json));

        protected static void ValidateOtherErrorCode(JsonElement json)
        {
            if (json.GetProperty("error_code").GetString() != "0")
                throw new TiebaException($"Error from tieba client, raw json:{json}");
        }

        protected static ArrayEnumerator EnsureNonEmptyPostList(JsonElement json, string fieldName, string exceptionMessage)
        {
            using var posts = json.GetProperty(fieldName).EnumerateArray();
            return posts.Any() ? posts : throw new TiebaException(exceptionMessage);
        }

        public static IEnumerable<TRevision> GetRevisionsForTwoObjectsThenSync<TObject, TRevision>(ILogger logger,
            string[] jsonTypePropsInObject,
            IEnumerable<TObject> newObjects,
            Func<TObject, TObject> oldObjectSelector,
            Func<uint, TObject, TRevision> revisionFactory)
        {
            var postProps = typeof(TObject).GetProperties()
                .Where(p => p.Name is not (nameof(IEntityWithTimestampFields.CreatedAt) or nameof(IEntityWithTimestampFields.UpdatedAt))).ToList();
            var postRevisionProps = typeof(TRevision).GetProperties();
            var nowTimestamp = (uint)DateTimeOffset.Now.ToUnixTimeSeconds();

            return newObjects.Select(newObj =>
            {
                var revision = default(TRevision);
                var oldObj = oldObjectSelector(newObj);
                foreach (var p in postProps)
                {
                    var newValue = p.GetValue(newObj);
                    var oldValue = p.GetValue(oldObj);
                    if (oldValue != null && jsonTypePropsInObject.Contains(p.Name))
                    { // serialize the value of json type fields which read from db
                      // for further compare with newValue which have been re-serialized in RawJsonOrNullWhenEmpty()
                        using var json = JsonDocument.Parse((string)oldValue);
                        oldValue = JsonSerializer.Serialize(json);
                    }

                    if (Equals(oldValue, newValue)) continue;
                    // ef core will track changes on oldPost via reflection
                    p.SetValue(oldObj, newValue);

                    var revisionProp = postRevisionProps.FirstOrDefault(p2 => p2.Name == p.Name);
                    if (revisionProp == null)
                        logger.LogWarning("updating field {} is not existed in revision table, " +
                                          "newValue={}, oldValue={}, newPost={}, oldPost={}",
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

        protected void DiffPosts<TPostRevision>(TbmDbContext db,
            string[] jsonTypePropsInPost,
            Func<TPost, bool> isExistPredicate,
            Func<TPost, TPost> existingPostSelector,
            Func<uint, TPost, TPostRevision> revisionFactory) where TPostRevision : IPostRevision
        {
            var existedOrNew = Posts.Values.ToLookup(isExistPredicate);
            db.AddRange((IEnumerable<object>)GetRevisionsForTwoObjectsThenSync(Logger, jsonTypePropsInPost, existedOrNew[true], existingPostSelector, revisionFactory));
            var newPostsPendingForInsert = ((IEnumerable<object>)existedOrNew[false]).ToList();
            if (newPostsPendingForInsert.Any()) db.AddRange(newPostsPendingForInsert);
        }

        protected void InsertPostsIndex(TbmDbContext db, IEnumerable<ulong> existingPostsIndex, Func<TPost, PostIndex> indexFactory) =>
            db.AddRange(Posts.GetValuesByKeys(Posts.Keys.Except(existingPostsIndex)).Select(indexFactory));
    }
}
