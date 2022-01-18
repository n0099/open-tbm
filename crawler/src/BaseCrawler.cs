using System;
using System.Collections.Generic;
using System.Text.Json;
using System.Threading.Tasks;

namespace tbm
{
    public abstract class BaseCrawler
    {
        protected int Fid { get; }
        protected uint StartPage { get; }
        protected uint EndPage { get; set; }
        protected List<IPost> Posts { get; set; } = new(50); // rn=50
        private ClientRequester ClientRequester { get; }

        protected BaseCrawler(int fid, uint startPage, uint? endPage, ClientRequester clientRequester)
        {
            Fid = fid;
            StartPage = startPage;
            EndPage = endPage ?? uint.MaxValue;
            ClientRequester = clientRequester;
        }

        public abstract Task DoCrawler();

        protected Exception AddExceptionData(Exception e)
        {
            e.Data["fid"] = Fid;
            e.Data["startPage"] = StartPage;
            e.Data["endPage"] = EndPage;
            return e;
        }

        protected async Task<JsonElement> CrawlSinglePage(string url, Dictionary<string, string> param)
        {
            var stream = (await ClientRequester.Post(url, param)).EnsureSuccessStatusCode().Content.ReadAsStream();
            using var doc = JsonDocument.Parse(stream);
            return doc.RootElement.Clone();
        }
    }
}
