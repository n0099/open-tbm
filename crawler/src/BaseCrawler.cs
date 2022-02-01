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

namespace tbm
{
    public abstract class BaseCrawler<TPost> where TPost : IPost
    {
        private readonly ILogger<BaseCrawler<TPost>> _logger;
        private readonly ClientRequesterTcs _clientRequesterTcs;
        protected abstract CrawlerLocks CrawlerLocks { get; init; } // singleton for every derived class
        protected ConcurrentDictionary<ulong, TPost> Posts { get; } = new();
        private readonly ClientRequester _requester;
        private readonly Fid _fid;

        protected abstract Exception FillExceptionData(Exception e);
        protected abstract Task<JsonElement> CrawlSinglePage(Page page);
        protected abstract ArrayEnumerator ValidateJson(JsonElement json);
        protected abstract void ParsePosts(ArrayEnumerator posts);

        protected BaseCrawler(ILogger<BaseCrawler<TPost>> logger, ClientRequester requester, ClientRequesterTcs requesterTcs, uint fid)
        {
            _logger = logger;
            _requester = requester;
            _clientRequesterTcs = requesterTcs;
            _fid = fid;
        }

        public async Task DoCrawler(Page startPage, Page endPage = Page.MaxValue)
        {
            try
            {
                var startPageEl = await CrawlSinglePage(startPage);
                ParsePosts(ValidateJson(startPageEl));
                endPage = Math.Min(Page.Parse(startPageEl.GetProperty("page").GetProperty("total_page").GetString() ?? ""), endPage);
                await DoCrawler(Enumerable.Range((int)(startPage + 1), (int)(endPage - startPage)).Select(i => (Page)i));
            }
            catch (Exception e)
            {
                e.Data["startPage"] = startPage;
                e.Data["endPage"] = endPage;
                e.Data["fid"] = _fid;
                _logger.LogError(FillExceptionData(e), "exception");
            }
        }

        private async Task DoCrawler(IEnumerable<Page> pages) =>
            await Task.WhenAll(CrawlerLocks.AddLocks(_fid, pages).Shuffle().Select(async page =>
            {
                try
                {
                    ParsePosts(ValidateJson(await CrawlSinglePage(page)));
                }
                catch (Exception e)
                {
                    e.Data["page"] = page;
                    e.Data["fid"] = _fid;
                    _logger.LogError(FillExceptionData(e), "exception");
                    _clientRequesterTcs.Decrease();
                    CrawlerLocks.AddFailed(_fid, page);
                }
                finally
                {
                    CrawlerLocks.ReleaseLock(_fid, page);
                }
            }));

        protected static void ValidateOtherErrorCode(JsonElement json)
        {
            if (json.GetProperty("error_code").GetString() != "0")
                throw new Exception($"Error from tieba client when crawling thread, raw json:{json}");
        }

        protected static ArrayEnumerator EnsureNonEmptyPostList(JsonElement json, string postListName, string exceptionMessage)
        {
            using var posts = json.GetProperty(postListName).EnumerateArray();
            return posts.Any() ? posts : throw new Exception(exceptionMessage);
        }

        protected static string? NullIfEmptyJsonLiteral(string json) => json is @"""""" or "[]" ? null : json;

        protected async Task<JsonElement> RequestJson(string url, Dictionary<string, string> param)
        {
            await using var stream = (await _requester.Post(url, param)).EnsureSuccessStatusCode().Content.ReadAsStream();
            using var doc = JsonDocument.Parse(stream);
            return doc.RootElement.Clone();
        }
    }
}
