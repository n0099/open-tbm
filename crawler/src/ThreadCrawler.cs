using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using Autofac.Features.Indexed;
using Microsoft.Extensions.Logging;
using static System.Text.Json.JsonElement;
using Page = System.UInt32;
using Fid = System.UInt32;
using Tid = System.UInt64;
using Pid = System.UInt64;
using Uid = System.Int64;
using Time = System.UInt32;

namespace tbm
{
    public sealed class ThreadCrawler : BaseCrawler<ThreadPost>
    {
        protected override CrawlerLocks CrawlerLocks { get; init; }
        private readonly string _forumName;

        public delegate ThreadCrawler New(Fid fid, string forumName);

        public ThreadCrawler(
            ILogger<ThreadCrawler> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            IIndex<string, CrawlerLocks.New> locks,
            Fid fid,
            string forumName
        ) : base(logger, requester, requesterTcs, fid)
        {
            _forumName = forumName;
            CrawlerLocks = locks["thread"]("thread");
        }

        protected override Exception FillExceptionData(Exception e)
        {
            e.Data["forumName"] = _forumName;
            return e;
        }

        protected override async Task<JsonElement> CrawlSinglePage(Page page) =>
            await RequestJson("http://c.tieba.baidu.com/c/f/frs/page", new Dictionary<string, string>
            {
                {"kw", _forumName},
                {"pn", page.ToString()},
                {"rn", "50"}
            });

        protected override ArrayEnumerator ValidateJson(JsonElement json)
        {
            ValidateOtherErrorCode(json);
            return EnsureNonEmptyPostList(json, "thread_list",
                "Forum threads list is empty, forum might doesn't existed");
        }

        protected override void ParsePosts(ArrayEnumerator posts)
        {
            var newPosts = posts.Select(p => new ThreadPost
            {
                Tid = Tid.Parse(p.GetStrProp("tid")),
                FirstPid = Pid.Parse(p.GetStrProp("first_post_id")),
                ThreadType = ulong.Parse(p.GetStrProp("thread_types")),
                StickyType = p.GetStrProp("is_membertop") == "1"
                    ? "membertop"
                    : p.TryGetProperty("is_top", out var isTop)
                        ? isTop.GetString() == "0" ? null : "top"
                        : "top", // in 6.0.2 client version, if there's a vip sticky thread and three normal sticky threads, the fourth (oldest) thread won't have is_top field
                IsGood = p.GetStrProp("is_good") == "1",
                TopicType = p.TryGetProperty("is_livepost", out var isLivePost) ? isLivePost.GetString() : null,
                Title = p.GetStrProp("title"),
                AuthorUid = Uid.Parse(p.GetProperty("author").GetStrProp("id")),
                AuthorManagerType = p.GetProperty("author").TryGetProperty("bawu_type", out var bawuType)
                    ? bawuType.GetString().NullIfWhiteSpace()
                    : null, // topic thread won't have this
                PostTime = p.TryGetProperty("create_time", out var createTime)
                    ? Time.Parse(createTime.GetString() ?? "")
                    : null, // topic thread won't have this
                LatestReplyTime = Time.Parse(p.GetStrProp("last_time_int")),
                LatestReplierUid = Uid.Parse(p.GetProperty("last_replyer").GetStrProp("id")), // topic thread won't have this
                ReplyNum = uint.Parse(p.GetStrProp("reply_num")),
                ViewNum = uint.Parse(p.GetStrProp("view_num")),
                ShareNum = p.TryGetProperty("share_num", out var shareNum)
                    ? uint.Parse(shareNum.GetString() ?? "")
                    : null, // topic thread won't have this
                AgreeNum = int.Parse(p.GetStrProp("agree_num")),
                DisagreeNum = int.Parse(p.GetStrProp("disagree_num")),
                Location = NullIfEmptyJsonLiteral(p.GetProperty("location").GetRawText()),
                ZanInfo = NullIfEmptyJsonLiteral(p.GetProperty("zan").GetRawText())
            });
            newPosts.ToList().ForEach(i => Posts[i.Tid] = i);
        }
    }
}
