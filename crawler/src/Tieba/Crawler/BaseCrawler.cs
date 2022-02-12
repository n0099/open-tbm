using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
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
        public abstract void SavePosts();

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

        public async Task<BaseCrawler<TPost>> DoCrawler(Page startPage, Page endPage = Page.MaxValue)
        {
            try
            {
                var startPageEl = await CrawlSinglePage(startPage);
                ValidateJsonThenParse(startPageEl);
                endPage = Math.Min(Page.Parse(startPageEl.GetProperty("page").GetProperty("total_page").GetString() ?? ""), endPage);
                await DoCrawler(Enumerable.Range((int)(startPage + 1), (int)(endPage - startPage)).Select(i => (Page)i));
            }
            catch (Exception e)
            {
                e.Data["startPage"] = startPage;
                e.Data["endPage"] = endPage;
                e.Data["fid"] = Fid;
                Logger.Log(e is TiebaException ? LogLevel.Warning : LogLevel.Error, FillExceptionData(e), "exception");
            }

            return this;
        }

        private async Task DoCrawler(IEnumerable<Page> pages) =>
            await Task.WhenAll(_locks.AddLocks(_lockIndex, pages).Shuffle().Select(async page =>
            {
                try
                {
                    ValidateJsonThenParse(await CrawlSinglePage(page));
                }
                catch (Exception e)
                {
                    e.Data["page"] = page;
                    e.Data["fid"] = Fid;
                    Logger.Log(e is TiebaException ? LogLevel.Warning : LogLevel.Error, FillExceptionData(e), "exception");
                    _requesterTcs.Decrease();
                    _locks.AddFailed(_lockIndex, page);
                }
                finally
                {
                    _locks.ReleaseLock(_lockIndex, page);
                }
            }));

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

        protected void DiffPosts<TPostRevision>(TbmDbContext db,
            Func<TPost, bool> existPredicate,
            Func<TPost, TPost> existingPostSelector,
            Func<uint, TPost, TPostRevision> postRevisionFactory) where TPostRevision : IPostRevision
        {
            var groupedPosts = Posts.Values.GroupBy(existPredicate).ToList();
            IEnumerable<TPost> GetExistedOrNewPosts(bool isExisted) =>
                groupedPosts.SingleOrDefault(i => i.Key == isExisted)?.ToList() ?? new List<TPost>();

            var postProps = typeof(TPost).GetProperties()
                .Where(p => p.Name is not (nameof(IPost.CreatedAt)
                    or nameof(IPost.UpdatedAt) or nameof(IPost.JsonTypeProps))).ToList();
            var postRevisionProps = typeof(TPostRevision).GetProperties();
            var nowTimestamp = (uint)DateTimeOffset.Now.ToUnixTimeSeconds();

            foreach (var newPost in GetExistedOrNewPosts(true))
            {
                var revision = default(TPostRevision);
                var oldPost = existingPostSelector(newPost);
                foreach (var p in postProps)
                {
                    var newValue = p.GetValue(newPost);
                    var oldValue = p.GetValue(oldPost);
                    if (oldValue != null && oldPost.JsonTypeProps.Contains(p.Name))
                    { // serialize the value of json type fields which read from db
                      // for further compare with newValue which have been re-serialized in RawJsonOrNullWhenEmpty()
                        using var json = JsonDocument.Parse((string)oldValue);
                        oldValue = JsonSerializer.Serialize(json);
                    }

                    if (Equals(oldValue, newValue)) continue;
                    // ef core will track changes on oldPost via reflection
                    p.SetValue(oldPost, newValue);

                    var revisionProp = postRevisionProps.FirstOrDefault(p2 => p2.Name == p.Name);
                    if (revisionProp == null)
                        Logger.LogWarning("updating field {} is not existed in revision table, " +
                                          "newValue={}, oldValue={}, newPost={}, oldPost={}",
                            p.Name, newValue, oldValue, JsonSerializer.Serialize(newPost), JsonSerializer.Serialize(oldPost));
                    else
                    {
                        revision ??= postRevisionFactory(nowTimestamp, newPost);
                        revisionProp.SetValue(revision, newValue);
                    }
                }

                if (revision != null) db.Add(revision);
            }

            var newPostsPendingForInsert = GetExistedOrNewPosts(false);
            if (newPostsPendingForInsert.Any()) db.AddRange();
        }
    }
}
