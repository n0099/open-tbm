using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using static System.Text.Json.JsonElement;

namespace tbm
{
    public class ThreadCrawler : BaseCrawler
    {
        public string ForumName { get; }

        public ThreadCrawler(ClientRequester clientRequester, string forumName, int fid, uint startPage, uint endPage = 1)
            : base(fid, startPage, endPage, clientRequester) => ForumName = forumName;

        public override async Task<ThreadCrawler> DoCrawler()
        {
            try
            {
                var startPageEl = await CrawlSinglePage(StartPage);
                ParseThreads(ValidateJson(startPageEl));
                EndPage = Math.Min(EndPage, uint.Parse(startPageEl.GetProperty("page").GetProperty("total_page").GetString() ?? ""));
            }
            catch (Exception e)
            {
                throw AddExceptionData(e);
            }
            var pages = Enumerable.Range((int)StartPage + 1, (int)EndPage).Select(i => (uint)i).Shuffle();
            foreach (var page in pages)
            {
                try
                {
                    ParseThreads(ValidateJson(await CrawlSinglePage(page)));
                    Console.WriteLine(Posts.Count);
                }
                catch (Exception e)
                {
                    e.Data["curPage"] = page;
                    throw;
                }
            }
            return this;
        }

        private void ParseThreads(ArrayEnumerator threads)
        {
            static string? NullIfEmptyJsonLiteral(string json) => json is @"""""" or "[]" ? null : json;
            var newThreads = threads.Select(t => new ThreadPost
            {
                Tid = ulong.Parse(t.GetStrProp("tid")),
                FirstPid = ulong.Parse(t.GetStrProp("first_post_id")),
                ThreadType = ulong.Parse(t.GetStrProp("thread_types")),
                StickyType = t.GetStrProp("is_membertop") == "1"
                    ? "membertop"
                    : t.TryGetProperty("is_top", out var isTop)
                        ? isTop.GetString() == "0" ? null : "top"
                        : "top", // in 6.0.2 client version, if there's a vip sticky thread and three normal sticky threads, the fourth (oldest) thread won't have is_top field
                IsGood = t.GetStrProp("is_good") == "1",
                TopicType = t.TryGetProperty("is_livepost", out var isLivePost) ? isLivePost.GetString() : null,
                Title = t.GetStrProp("title"),
                AuthorUid = long.Parse(t.GetProperty("author").GetStrProp("id")),
                AuthorManagerType = t.GetProperty("author").TryGetProperty("bawu_type", out var bawuType) ? bawuType.GetString().NullIfWhiteSpace() : null, // topic thread won't have this
                PostTime = t.TryGetProperty("create_time", out var createTime) ? uint.Parse(createTime.GetString() ?? "") : null, // topic thread won't have this
                LatestReplyTime = uint.Parse(t.GetStrProp("last_time_int")),
                LatestReplierUid = long.Parse(t.GetProperty("last_replyer").GetStrProp("id")), // topic thread won't have this
                ReplyNum = uint.Parse(t.GetStrProp("reply_num")),
                ViewNum = uint.Parse(t.GetStrProp("view_num")),
                ShareNum = t.TryGetProperty("share_num", out var shareNum) ? uint.Parse(shareNum.GetString() ?? "") : null, // topic thread won't have this
                AgreeNum = int.Parse(t.GetStrProp("agree_num")),
                DisagreeNum = int.Parse(t.GetStrProp("disagree_num")),
                Location = NullIfEmptyJsonLiteral(t.GetProperty("location").GetRawText()),
                ZanInfo = NullIfEmptyJsonLiteral(t.GetProperty("zan").GetRawText())
            });
            Posts = Posts.Concat(newThreads).ToList();
        }

        private static ArrayEnumerator ValidateJson(JsonElement json)
        {
            if (json.GetProperty("error_code").GetString() != "0")
                throw new Exception($"Error from tieba client when crawling thread, raw json:{json}");
            var threads = json.GetProperty("thread_list").EnumerateArray();
            if (!threads.Any()) throw new Exception("Forum threads list is empty, forum might doesn't existed");
            return threads;
        }

        private async Task<JsonElement> CrawlSinglePage(uint page) => 
            await CrawlSinglePage("http://c.tieba.baidu.com/c/f/frs/page", new Dictionary<string, string>
            {
                { "kw", ForumName },
                { "pn", page.ToString() },
                { "rn", "50" }
            });
    }
}
