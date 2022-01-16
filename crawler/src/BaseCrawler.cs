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
        private ClientRequester ClientRequester { get; }

        protected BaseCrawler(int fid, uint startPage, uint? endPage, ClientRequester clientRequester)
        {
            Fid = fid;
            StartPage = startPage;
            EndPage = endPage ?? uint.MaxValue;
            ClientRequester = clientRequester;
        }

        public abstract Task DoCrawler();
        protected async Task<JsonElement> CrawlSinglePage(string url, Dictionary<string, string> param) =>
            (await JsonDocument.ParseAsync(
                await(await ClientRequester.Post(url, param)).EnsureSuccessStatusCode().Content.ReadAsStreamAsync())).RootElement;
    }
}
