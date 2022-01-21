using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using static System.Text.Json.JsonElement;
using Fid = System.UInt32;
using Tid = System.UInt64;
using Page = System.UInt32;

namespace tbm
{
    public abstract class BaseCrawler
    {
        protected Fid Fid { get; }
        protected abstract CrawlerLocks CrawlerLocks { get; } // singleton
        protected ConcurrentDictionary<Tid, IPost> Posts { get; } = new(5, 50); // rn=50
        private readonly ClientRequester _clientRequester;
        public abstract Task DoCrawler(IEnumerable<Page> pages);
        protected abstract void ParseThreads(ArrayEnumerator threads);
        protected abstract ArrayEnumerator ValidateJson(JsonElement json);
        protected abstract Task<JsonElement> CrawlSinglePage(Page page);

        protected BaseCrawler(ClientRequester clientRequester, Fid fid)
        {
            _clientRequester = clientRequester;
            Fid = fid;
        }

        public async Task DoCrawler(Page startPage, Page endPage = Page.MaxValue)
        {
            try
            {
                var startPageEl = await CrawlSinglePage(startPage);
                ParseThreads(ValidateJson(startPageEl));
                endPage = Math.Min(Page.Parse(startPageEl.GetProperty("page").GetProperty("total_page").GetString() ?? ""), endPage);
            }
            catch (Exception e)
            {
                e.Data["startPage"] = startPage;
                e.Data["endPage"] = endPage;
                throw FillExceptionData(e);
            }

            await DoCrawler(Enumerable.Range((int)(startPage + 1), (int)(endPage - startPage)).Select(i => (Page)i));
        }

        protected Exception FillExceptionData(Exception e)
        {
            e.Data["fid"] = Fid;
            return e;
        }

        protected static void ValidateErrorCode(JsonElement json)
        {
            if (json.GetProperty("error_code").GetString() != "0")
                throw new Exception($"Error from tieba client when crawling thread, raw json:{json}");
        }

        protected async Task<JsonElement> RequestJson(string url, Dictionary<string, string> param)
        {
            var stream = (await _clientRequester.Post(url, param)).EnsureSuccessStatusCode().Content.ReadAsStream();
            using var doc = JsonDocument.Parse(stream);
            return doc.RootElement.Clone();
        }
    }
}
