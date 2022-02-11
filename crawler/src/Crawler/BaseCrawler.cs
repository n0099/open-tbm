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
        protected readonly Fid Fid;
        protected abstract CrawlerLocks CrawlerLocks { get; init; } // singleton for every derived class
        protected readonly ConcurrentDictionary<ulong, TPost> Posts = new();
        protected readonly UserParser Users;

        protected abstract Exception FillExceptionData(Exception e);
        protected abstract Task<JsonElement> CrawlSinglePage(Page page);
        protected abstract ArrayEnumerator GetValidPosts(JsonElement json);
        protected abstract void ParsePosts(ArrayEnumerator posts);
        public abstract void SavePosts();

        protected BaseCrawler(ILogger<BaseCrawler<TPost>> logger, ClientRequester requester, ClientRequesterTcs requesterTcs, UserParser userParser,uint fid)
        {
            Logger = logger;
            _requester = requester;
            _requesterTcs = requesterTcs;
            Fid = fid;
            Users = userParser;
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
            await Task.WhenAll(CrawlerLocks.AddLocks(Fid, pages).Shuffle().Select(async page =>
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
                    CrawlerLocks.AddFailed(Fid, page);
                }
                finally
                {
                    CrawlerLocks.ReleaseLock(Fid, page);
                }
            }));

        protected virtual void ValidateJsonThenParse(JsonElement json) => ParsePosts(GetValidPosts(json));

        protected static void ValidateOtherErrorCode(JsonElement json)
        {
            if (json.GetProperty("error_code").GetString() != "0")
                throw new TiebaException($"Error from tieba client when crawling thread, raw json:{json}");
        }

        protected static ArrayEnumerator EnsureNonEmptyPostList(JsonElement json, string fieldName, string exceptionMessage)
        {
            using var posts = json.GetProperty(fieldName).EnumerateArray();
            return posts.Any() ? posts : throw new TiebaException(exceptionMessage);
        }

        protected async Task<JsonElement> RequestJson(string url, Dictionary<string, string> param)
        {
            await using var stream = (await _requester.Post(url, param)).EnsureSuccessStatusCode().Content.ReadAsStream();
            using var doc = JsonDocument.Parse(stream);
            return doc.RootElement.Clone();
        }
    }
}
