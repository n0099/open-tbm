using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Text.Json;
using System.Threading.Tasks;
using Fid = System.UInt32;
using Tid = System.UInt64;
using Page = System.UInt16;
using Time = System.UInt32;

namespace tbm
{
    public abstract class BaseCrawler<T> where T : BaseCrawler<T>
    {
        protected Fid Fid { get; }
        protected Page StartPage { get; }
        protected Page EndPage { get; set; }
        protected ConcurrentDictionary<Tid, IPost> Posts { get; } = new(1, 50); // rn=50
        protected static ConcurrentDictionary<Fid, ConcurrentDictionary<Page, Time>> CrawlingThreads { get; } = new(5, 10);
        protected static ConcurrentDictionary<Fid, ConcurrentDictionary<Page, ushort>> FailedThreads { get; } = new(5, 10);
        protected const ushort RetryAfter = 300; // 5 minutes
        private readonly ClientRequester _clientRequester;

        protected BaseCrawler(Fid fid, Page startPage, Page? endPage, ClientRequester clientRequester)
        {
            Fid = fid;
            StartPage = startPage;
            EndPage = endPage ?? Page.MaxValue;
            _clientRequester = clientRequester;
        }

        public abstract Task<T> DoCrawler();
        public abstract Task<T> DoCrawler(IEnumerable<Page> pages);

        protected Exception FillExceptionData(Exception e)
        {
            e.Data["fid"] = Fid;
            e.Data["startPage"] = StartPage;
            e.Data["endPage"] = EndPage;
            return e;
        }

        protected async Task<JsonElement> RequestJson(string url, Dictionary<string, string> param)
        {
            var stream = (await ClientRequester.Post(url, param)).EnsureSuccessStatusCode().Content.ReadAsStream();
            var stream = (await _clientRequester.Post(url, param)).EnsureSuccessStatusCode().Content.ReadAsStream();
            using var doc = JsonDocument.Parse(stream);
            return doc.RootElement.Clone();
        }
    }
}
