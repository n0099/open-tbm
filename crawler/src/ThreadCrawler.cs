using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using static System.Text.Json.JsonElement;
using Fid = System.UInt32;
using Tid = System.UInt64;
using Pid = System.UInt64;
using Uid = System.Int64;
using Page = System.UInt16;
using Time = System.UInt32;

namespace tbm
{
    public class ThreadCrawler : BaseCrawler<ThreadCrawler>
    {
        public string ForumName { get; }

        public ThreadCrawler(ClientRequester clientRequester, string forumName, Fid fid, Page startPage, Page endPage = 1)
            : base(fid, startPage, endPage, clientRequester) => ForumName = forumName;

        public override async Task<ThreadCrawler> DoCrawler()
        {
            try
            {
                var startPageEl = await CrawlSinglePage(StartPage);
                ParseThreads(ValidateJson(startPageEl));
                EndPage = Math.Min(EndPage, Page.Parse(startPageEl.GetProperty("page").GetProperty("total_page").GetString() ?? ""));
            }
            catch (Exception e)
            {
                throw FillExceptionData(e);
            }

            return await DoCrawler(Enumerable.Range(StartPage + 1, EndPage - StartPage).Select(i => (Page)i));
        }

        public override async Task<ThreadCrawler> DoCrawler(IEnumerable<Page> pages)
        {
            var deduplicatedPages = pages.ToList();
            lock (CrawlingThreads)
            { // lock the entire ConcurrentDictionary since following bulk insert should be a single atomic operation
                foreach (var page in deduplicatedPages.ToList()) // iterate on copy
                {
                    var now = (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
                    var newFid = new ConcurrentDictionary<Page, Time>(5, 10);
                    newFid.TryAdd(page, now);
                    if (CrawlingThreads.TryAdd(Fid, newFid)) continue;
                    if (CrawlingThreads[Fid].TryAdd(page, now)) continue;

                    if (CrawlingThreads[Fid][page] < now - RetryAfter)
                        CrawlingThreads[Fid][page] = now;
                    else deduplicatedPages.Remove(page);
                }
            }
            
            await Task.WhenAll(deduplicatedPages.Shuffle().Select(async page =>
            {
                try
                {
                    ParseThreads(ValidateJson(await CrawlSinglePage(page)));
                }
                catch (Exception e)
                {
                    var newFid = new ConcurrentDictionary<Page, ushort>(5, 10);
                    newFid.TryAdd(page, 1);
                    if (!FailedThreads.TryAdd(Fid, newFid))
                        lock (FailedThreads)
                            if (!FailedThreads[Fid].TryAdd(page, 1))
                                FailedThreads[Fid][page]++;

                    e.Data["curPage"] = page;
                    Program.LogException(FillExceptionData(e));
                }
                finally
                {
                    CrawlingThreads[Fid].TryRemove(page, out _);
                    Console.WriteLine("c3:" + CrawlingThreads[Fid].Count);
                    lock(CrawlingThreads)
                        if (CrawlingThreads[Fid].IsEmpty)
                            CrawlingThreads.TryRemove(Fid, out _);
                }
            }));
            Console.WriteLine(Posts.Count);

            return this;
        }

        private void ParseThreads(ArrayEnumerator threads)
        {
            static string? NullIfEmptyJsonLiteral(string json) => json is @"""""" or "[]" ? null : json;
            var newThreads = threads.Select(t => new ThreadPost
            {
                Tid = Tid.Parse(t.GetStrProp("tid")),
                FirstPid = Pid.Parse(t.GetStrProp("first_post_id")),
                ThreadType = ulong.Parse(t.GetStrProp("thread_types")),
                StickyType = t.GetStrProp("is_membertop") == "1"
                    ? "membertop"
                    : t.TryGetProperty("is_top", out var isTop)
                        ? isTop.GetString() == "0" ? null : "top"
                        : "top", // in 6.0.2 client version, if there's a vip sticky thread and three normal sticky threads, the fourth (oldest) thread won't have is_top field
                IsGood = t.GetStrProp("is_good") == "1",
                TopicType = t.TryGetProperty("is_livepost", out var isLivePost) ? isLivePost.GetString() : null,
                Title = t.GetStrProp("title"),
                AuthorUid = Uid.Parse(t.GetProperty("author").GetStrProp("id")),
                AuthorManagerType = t.GetProperty("author").TryGetProperty("bawu_type", out var bawuType) ? bawuType.GetString().NullIfWhiteSpace() : null, // topic thread won't have this
                PostTime = t.TryGetProperty("create_time", out var createTime) ? Time.Parse(createTime.GetString() ?? "") : null, // topic thread won't have this
                LatestReplyTime = Time.Parse(t.GetStrProp("last_time_int")),
                LatestReplierUid = Uid.Parse(t.GetProperty("last_replyer").GetStrProp("id")), // topic thread won't have this
                ReplyNum = uint.Parse(t.GetStrProp("reply_num")),
                ViewNum = uint.Parse(t.GetStrProp("view_num")),
                ShareNum = t.TryGetProperty("share_num", out var shareNum) ? uint.Parse(shareNum.GetString() ?? "") : null, // topic thread won't have this
                AgreeNum = int.Parse(t.GetStrProp("agree_num")),
                DisagreeNum = int.Parse(t.GetStrProp("disagree_num")),
                Location = NullIfEmptyJsonLiteral(t.GetProperty("location").GetRawText()),
                ZanInfo = NullIfEmptyJsonLiteral(t.GetProperty("zan").GetRawText())
            });
            newThreads.ToList().ForEach(i => Posts[i.Tid] = i); // newThreads will overwrite Posts with same tid
        }

        private static ArrayEnumerator ValidateJson(JsonElement json)
        {
            if (json.GetProperty("error_code").GetString() != "0")
                throw new Exception($"Error from tieba client when crawling thread, raw json:{json}");
            var threads = json.GetProperty("thread_list").EnumerateArray();
            if (!threads.Any()) throw new Exception("Forum threads list is empty, forum might doesn't existed");
            return threads;
        }

        private async Task<JsonElement> CrawlSinglePage(Page page) =>
            await RequestJson("http://c.tieba.baidu.com/c/f/frs/page", new Dictionary<string, string>
            {
                {"kw", ForumName},
                {"pn", page.ToString()},
                {"rn", "50"}
            });
    }
}
